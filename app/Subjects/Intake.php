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

        $fresh = $candidates->reject(fn ($seenIn, $name) => $known->contains(Subject::slugFor((string) $name)));
        $outcomes['known'] = $candidates->count() - $fresh->count();

        // The cap is per day, not per run: a re-run of the same day only fills what is left of it.
        $room = config('trend.discovery.max_candidates')
            - Subject::whereDate('first_seen_on', $day->toDateString())->whereRelation('events', 'type', 'discovered')->count();

        foreach ($fresh->take(max(0, $room)) as $name => $seenIn) {
            [$state, $payload, $label] = $this->route((string) $name, $seenIn);

            $this->create((string) $name, $state, $day, 'discovered', $payload, $label);
            $outcomes[$state->value]++;
        }

        return $outcomes;
    }

    /** A Seed skips the Classifier and starts in Watching; seeding a Backlog or Archived Subject promotes it. */
    public function seed(string $name, ?string $query = null): Subject
    {
        $name = trim($name);

        if (Subject::slugFor($name) === '') {
            throw new InvalidArgumentException("A Seed needs a name with letters or digits, got [{$name}].");
        }

        $subject = Subject::where('slug', Subject::slugFor($name))->first();

        if ($subject === null) {
            return $this->create($name, SubjectState::Watching, CarbonImmutable::now('UTC')->startOfDay(), 'seeded', [], null, $query);
        }

        if (filled($query)) {
            $subject->update(['query' => trim($query)]);
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

    /** @param array<string, mixed> $payload */
    private function create(string $name, SubjectState $state, CarbonImmutable $day, string $event, array $payload, ?string $label, ?string $query = null): Subject
    {
        return DB::transaction(function () use ($name, $state, $day, $event, $payload, $label, $query) {
            $subject = Subject::create([
                'name' => $name,
                'slug' => Subject::slugFor($name),
                'query' => $query ?: $name,
                'state' => $state,
                'first_seen_on' => $day->toDateString(),
            ]);

            if ($label !== null) {
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
