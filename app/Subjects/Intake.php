<?php

namespace App\Subjects;

use App\Enums\SubjectState;
use App\Models\Event;
use App\Models\Item;
use App\Models\Label;
use App\Models\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * How Subjects come to exist: Candidates the Classifier routes (ADR-0006), and
 * Seeds the owner names by hand. A name already known is never created twice.
 */
final class Intake
{
    public function __construct(
        private readonly Discovery $discovery,
        private readonly Jev $classifier,
    ) {}

    /**
     * Turn one day's Items into Subjects, at most the daily caps of them. Names
     * already known are skipped before the caps, so names ruled out once never
     * crowd out new ones; Candidates over a cap are proposed again if they recur.
     *
     * @return array<string, int> how many Candidates went to each outcome
     */
    public function discover(CarbonImmutable $day): array
    {
        $items = Item::with('source')->where('observed_on', $day->toDateString())->get();
        $candidates = collect($this->discovery->candidates($items));
        $known = Subject::whereIn('slug', $candidates->keys()->map(fn ($name) => Subject::slugFor((string) $name)))->pluck('slug');
        $outcomes = ['known' => 0, 'watching' => 0, 'backlog' => 0, 'archived' => 0];

        $fresh = $candidates->reject(fn ($candidate, $name) => $known->contains(Subject::slugFor((string) $name)));
        $outcomes['known'] = $candidates->count() - $fresh->count();
        $today = fn () => Subject::whereDate('first_seen_on', $day->toDateString())->whereRelation('events', 'type', 'discovered');

        // App Store climbers have their own cap (ADR-0011): biggest climbs first, until the week's places are
        // filled. One the Classifier rules out takes no place, so at most `max_classified` are asked (ADR-0012).
        // The caps are per day, not per run: a re-run of the same day only fills what is left of them.
        $climbed = fn () => $today()->whereHas('events', fn ($query) => $query->where('type', 'discovered')->whereNotNull('payload->climb'));
        $places = config('trend.apple_ads.max_candidates') - $climbed()->where('state', '!=', SubjectState::Archived->value)->count();
        $asks = config('trend.apple_ads.max_classified') - $climbed()->count();

        foreach ($fresh->filter(fn (array $candidate) => $candidate['climb'] !== null)->sortByDesc('climb') as $name => $candidate) {
            if ($places <= 0 || $asks <= 0) {
                break;
            }

            $state = $this->admit((string) $name, $candidate, $day);
            $outcomes[$state->value]++;
            $asks--;
            $places -= $state === SubjectState::Archived ? 0 : 1;
        }

        $room = config('trend.discovery.max_candidates')
            - $today()->whereDoesntHave('events', fn ($query) => $query->where('type', 'discovered')->whereNotNull('payload->climb'))->count();

        foreach ($fresh->filter(fn (array $candidate) => $candidate['climb'] === null)->take(max(0, $room)) as $name => $candidate) {
            $outcomes[$this->admit((string) $name, $candidate, $day)->value]++;
        }

        return $outcomes;
    }

    /**
     * App Store Subjects proposed before the Classifier was asked whether they are
     * a need (ADR-0012), still in Watching or Backlog and never moved by a run or
     * the owner, are asked once and routed as Intake routes a Candidate today.
     *
     * @return array<string, string> slug => the state each moved to
     */
    public function reclassify(): array
    {
        if (! $this->classifier->configured()) {
            return [];
        }

        $moved = [];
        $subjects = Subject::whereIn('state', [SubjectState::Watching->value, SubjectState::Backlog->value])
            ->whereHas('events', fn ($query) => $query->where('type', 'discovered')->whereNotNull('payload->climb')->whereNull('payload->need'))
            ->whereDoesntHave('events', fn ($query) => $query->whereIn('type', ['reclassified', 'state_changed', 'seeded']))
            ->orderBy('id')
            ->get();

        foreach ($subjects as $subject) {
            $discovered = $subject->events()->where('type', 'discovered')->first();
            [$state, $payload, $label] = $this->route($subject->name, $discovered->payload['seen_in'] ?? [], true);

            if (! isset($payload['need'])) {
                continue; // the Classifier failed; asked again on the next run
            }

            DB::transaction(function () use ($subject, $state, $payload, $label, &$moved) {
                // A run or the owner may have moved it while the Classifier answered.
                $subject = Subject::whereKey($subject->id)->whereIn('state', [SubjectState::Watching->value, SubjectState::Backlog->value])
                    ->whereDoesntHave('events', fn ($query) => $query->whereIn('type', ['reclassified', 'state_changed', 'seeded']))
                    ->lockForUpdate()
                    ->first();

                if ($subject === null) {
                    return;
                }

                Event::create(['subject_id' => $subject->id, 'type' => 'reclassified', 'payload' => $payload, 'happened_at' => now()]);

                if ($label !== null) {
                    $subject->labels()->syncWithoutDetaching([Label::firstOrCreate(['name' => $label])->id]);
                }

                if ($state !== $subject->state) {
                    $subject->moveTo($state, match ($state) {
                        SubjectState::Archived => 'a search for one brand, app or event',
                        SubjectState::Watching => 'a search for a kind of app',
                        default => 'the Classifier was unsure',
                    }, $payload);
                    $moved[$subject->slug] = $state->value;
                }
            });
        }

        return $moved;
    }

    /** @param array{seen_in: list<string>, labels: list<string>, climb: ?int} $candidate */
    private function admit(string $name, array $candidate, CarbonImmutable $day): SubjectState
    {
        [$state, $payload, $label] = $this->route($name, $candidate['seen_in'], $candidate['climb'] !== null);

        if ($candidate['climb'] !== null) {
            $payload['climb'] = $candidate['climb'];
        }

        $this->create($name, $state, $day, 'discovered', $payload, [$label, ...$candidate['labels']]);

        return $state;
    }

    /** A Seed skips the Classifier and starts in Watching; seeding a Backlog or Archived Subject promotes it. */
    public function seed(string $name, ?string $query = null): Subject
    {
        $name = trim($name);

        if (Subject::slugFor($name) === '' || count(preg_split('/\s+/', $name)) > 5) {
            throw new InvalidArgumentException("A Seed is named in five words or fewer, with letters or digits; got [{$name}].");
        }

        $subject = Subject::where('slug', Subject::slugFor($name))->first();

        if ($subject === null) {
            return $this->create($name, SubjectState::Watching, CarbonImmutable::now('UTC')->startOfDay(), 'seeded', [], [], $query);
        }

        // A new query measures something else, so it starts a new series (rows are keyed by query).
        if (filled($query) && trim($query) !== $subject->query) {
            $subject->update(['query' => trim($query), 'scored_week' => null]);
        }

        // The owner chose it: no later reclassification may overrule that (ADR-0012).
        Event::create(['subject_id' => $subject->id, 'type' => 'seeded', 'payload' => ['query' => $subject->query], 'happened_at' => now()]);

        if (in_array($subject->state, [SubjectState::Backlog, SubjectState::Archived], true)) {
            $subject->moveTo(SubjectState::Watching, 'seeded by the owner');
        }

        return $subject;
    }

    /**
     * A Candidate the Classifier rules out is kept as Archived, so it is never classified again.
     *
     * @param  list<string>  $seenIn
     * @param  bool  $appStore  an App Store search term, asked whether it is a need (ADR-0012)
     * @return array{0: SubjectState, 1: array<string, mixed>, 2: ?string}
     */
    private function route(string $name, array $seenIn, bool $appStore = false): array
    {
        if (! $this->classifier->configured()) {
            return [SubjectState::Backlog, ['seen_in' => $seenIn, 'classifier' => 'not configured'], null];
        }

        try {
            $answer = $this->classifier->classify($name, $seenIn, $appStore);
        } catch (Throwable $e) {
            return [SubjectState::Backlog, ['seen_in' => $seenIn, 'classifier' => 'failed: '.Str::limit($e->getMessage(), 200)], null];
        }

        $state = match (true) {
            $answer->probability >= config('trend.classifier.track_at') => SubjectState::Watching,
            $answer->probability >= config('trend.classifier.backlog_at') => SubjectState::Backlog,
            default => SubjectState::Archived,
        };

        return [$state, ['seen_in' => $seenIn, $answer->question => $answer->probability, 'label' => $answer->label], $answer->label];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<?string>  $labels  the Classifier's Label and any its Source gave, nulls skipped
     */
    private function create(string $name, SubjectState $state, CarbonImmutable $day, string $event, array $payload, array $labels, ?string $query = null): Subject
    {
        return DB::transaction(function () use ($name, $state, $day, $event, $payload, $labels, $query) {
            $subject = Subject::create([
                'name' => $name,
                'slug' => Subject::slugFor($name),
                'query' => $query ?: $name,
                'state' => $state,
                'first_seen_on' => $day->toDateString(),
            ]);

            foreach (array_unique(array_filter($labels)) as $label) {
                $subject->labels()->attach(Label::firstOrCreate(['name' => $label]));
            }

            Event::create([
                'subject_id' => $subject->id,
                'type' => $event,
                'to_state' => $state->value,
                'payload' => $payload,
                'happened_at' => now(),
            ]);

            return $subject;
        });
    }
}
