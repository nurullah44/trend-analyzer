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
     * Turn one day's Items into Subjects, at most the daily cap of them. Names
     * already known are skipped before the cap, so names ruled out once never
     * crowd out new ones; Candidates over the cap are proposed again if they recur.
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

        // The caps are per day, not per run: a re-run of the same day only fills what is left of them.
        $room = config('trend.discovery.max_candidates')
            - Subject::whereDate('first_seen_on', $day->toDateString())->whereRelation('events', 'type', 'discovered')->count();

        // App Store climbers have their own cap, counted after known names are skipped (ADR-0011).
        $climbers = config('trend.apple_ads.max_candidates')
            - Subject::whereDate('first_seen_on', $day->toDateString())
                ->whereHas('events', fn ($query) => $query->where('type', 'discovered')->whereNotNull('payload->climb'))
                ->count();
        $admitted = $fresh->filter(fn (array $candidate) => $candidate['climb'] !== null)
            ->sortByDesc('climb')
            ->take(max(0, $climbers))
            ->keys();
        $fresh = $fresh->filter(fn (array $candidate, $name) => $candidate['climb'] === null || $admitted->contains($name));

        foreach ($fresh->take(max(0, $room)) as $name => $candidate) {
            [$state, $payload, $label] = $this->route((string) $name, $candidate['seen_in']);

            if ($candidate['climb'] !== null) {
                $payload['climb'] = $candidate['climb'];
            }

            $this->create((string) $name, $state, $day, 'discovered', $payload, [$label, ...$candidate['labels']]);
            $outcomes[$state->value]++;
        }

        return $outcomes;
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

        if (in_array($subject->state, [SubjectState::Backlog, SubjectState::Archived], true)) {
            $subject->moveTo(SubjectState::Watching, 'seeded by the owner');
        }

        return $subject;
    }

    /**
     * @param  list<string>  $seenIn
     *                                A Candidate the Classifier rules out is kept as Archived, so it is never classified again.
     * @return array{0: SubjectState, 1: array<string, mixed>, 2: ?string}
     */
    private function route(string $name, array $seenIn): array
    {
        if (! $this->classifier->configured()) {
            return [SubjectState::Backlog, ['seen_in' => $seenIn, 'classifier' => 'not configured'], null];
        }

        try {
            $answer = $this->classifier->classify($name, $seenIn);
        } catch (Throwable $e) {
            return [SubjectState::Backlog, ['seen_in' => $seenIn, 'classifier' => 'failed: '.Str::limit($e->getMessage(), 200)], null];
        }

        $state = match (true) {
            $answer->specific >= config('trend.classifier.track_at') => SubjectState::Watching,
            $answer->specific >= config('trend.classifier.backlog_at') => SubjectState::Backlog,
            default => SubjectState::Archived,
        };

        return [$state, ['seen_in' => $seenIn, 'specific' => $answer->specific, 'label' => $answer->label], $answer->label];
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
