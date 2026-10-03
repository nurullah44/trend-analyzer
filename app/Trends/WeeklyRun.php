<?php

namespace App\Trends;

use App\Enums\SubjectState;
use App\Models\Subject;
use Carbon\CarbonImmutable;

/**
 * The weekly run: measure every tracked Subject's missing weeks, score the
 * week, and move each Subject the stored numbers say it should move.
 */
final class WeeklyRun
{
    /** The states that are measured and scored every week. */
    public const TRACKED = [SubjectState::Watching, SubjectState::Rising, SubjectState::Trending, SubjectState::Detrending];

    public function __construct(
        private readonly Series $series,
        private readonly Scorer $scorer,
        private readonly States $states,
    ) {}

    /**
     * A Subject only moves on a complete week — every measurement Source answered —
     * and never on a week older than the last one it was scored on, so a failed
     * measurement or a historical re-run cannot move it.
     *
     * @return array{scored: int, moved: array<string, string>, failures: array<string, string>}
     */
    public function run(CarbonImmutable $week): array
    {
        $result = ['scored' => 0, 'moved' => [], 'failures' => []];
        $day = $week->toDateString();

        foreach (Subject::whereIn('state', self::TRACKED)->orderBy('id')->get() as $subject) {
            $failures = $this->series->measure($subject, $week);
            $result['failures'] += $failures;

            if ($failures !== [] || ($subject->scored_week !== null && $day < $subject->scored_week)) {
                continue;
            }

            $score = $this->scorer->score($this->series->of($subject), $day);
            $next = $this->states->next($subject->state, $score, $this->daysWatching($subject, $week));
            $subject->update(['scored_week' => $day]);
            $result['scored']++;

            if ($next !== null) {
                [$state, $reason] = $next;
                $subject->moveTo($state, $reason, ['week' => $day, ...$score->toArray()]);
                $result['moved'][$subject->slug] = $state->value;
            }
        }

        return $result;
    }

    /** Days from when the Subject last entered Watching to the end of the scored week; never negative. */
    private function daysWatching(Subject $subject, CarbonImmutable $week): int
    {
        $since = $subject->events()->where('to_state', SubjectState::Watching->value)->max('happened_at')
            ?? $subject->first_seen_on;

        return max(0, (int) CarbonImmutable::parse($since)->diffInDays($week->addWeek(), absolute: false));
    }
}
