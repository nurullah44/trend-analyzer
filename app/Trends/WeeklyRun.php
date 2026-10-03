<?php

namespace App\Trends;

use App\Collection\SourceRegistry;
use App\Collection\Sources\GoogleAds;
use App\Enums\SubjectState;
use App\Models\Source;
use App\Models\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The weekly run: measure every tracked Subject's missing weeks, score the
 * week, size the rising ones with Google Ads, close the ones a Mainstream
 * marker says have arrived, move the rest as the stored numbers say, and
 * publish an Alarm for each one that enters Trending.
 */
final class WeeklyRun
{
    /** The states that are measured and scored every week. */
    public const TRACKED = [SubjectState::Watching, SubjectState::Rising, SubjectState::Trending, SubjectState::Detrending];

    public function __construct(
        private readonly Series $series,
        private readonly Scorer $scorer,
        private readonly States $states,
        private readonly Alarms $alarms,
        private readonly Mainstream $mainstream,
        private readonly GoogleAds $googleAds,
        private readonly SourceRegistry $sources,
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
            $current = $subject->scored_week === null || $day >= $subject->scored_week;

            // Wikipedia first: one request tells a household name apart, and it is never asked the rest.
            $failures = $this->series->measure($subject, $week, ['wikimedia']);

            if ($current && $failures === [] && ($why = $this->mainstream->arrived($subject, $this->series->of($subject), $week)) !== null) {
                $subject->update(['scored_week' => $day]);
                $this->arrive($subject, $why, ['week' => $day]);
                $result['moved'][$subject->slug] = SubjectState::Mainstream->value;

                continue;
            }

            $failures += $this->series->measure($subject, $week, $this->otherThan('wikimedia'));
            $result['failures'] += $failures;

            if ($failures !== [] || ! $current) {
                continue;
            }

            $series = $this->series->of($subject);
            $score = $this->scorer->score($series, $day);
            $next = $this->states->next($subject->state, $score, $this->daysWatching($subject, $week));
            $subject->update(['scored_week' => $day]);
            $result['scored']++;

            $sized = [SubjectState::Rising, SubjectState::Trending];

            if (in_array($subject->state, $sized, true) || in_array($next[0] ?? null, $sized, true)) {
                $this->validate($subject);
            }

            if (($why = $this->mainstream->arrived($subject, $series, $week)) !== null) {
                $this->arrive($subject, $why, ['week' => $day, ...$score->toArray()]);
                $result['moved'][$subject->slug] = SubjectState::Mainstream->value;

                continue;
            }

            if ($next !== null) {
                [$state, $reason] = $next;

                DB::transaction(function () use ($subject, $state, $reason, $day, $score, $series, $week) {
                    $subject->moveTo($state, $reason, ['week' => $day, ...$score->toArray()]);

                    if ($state === SubjectState::Trending) {
                        $this->alarms->publish($subject, $score, $series, $week);
                    }
                });

                $result['moved'][$subject->slug] = $state->value;
            }
        }

        return $result;
    }

    /** @return list<string> every measurement Source except the given one */
    private function otherThan(string $key): array
    {
        return array_values(array_diff(array_keys($this->sources->measurements()), [$key]));
    }

    /**
     * Mainstream closes the Subject's open Alarms and fixes the lead time: the days
     * from its first Alarm to today. A Subject that never alarmed has none.
     *
     * @param  array<string, mixed>  $numbers
     */
    private function arrive(Subject $subject, string $why, array $numbers): void
    {
        DB::transaction(function () use ($subject, $why, $numbers) {
            $firstAlarm = $subject->alarms()->orderBy('published_on')->first();

            $subject->moveTo(SubjectState::Mainstream, $why, $numbers);
            $subject->update([
                'mainstream_on' => now()->toDateString(),
                'lead_time_days' => $firstAlarm === null ? null : (int) $firstAlarm->published_on->diffInDays(now()->startOfDay()),
            ]);
            $subject->alarms()->whereNull('closed_on')->update(['closed_on' => now()->toDateString()]);
        });
    }

    /**
     * Size a Rising or Trending Subject with Google Ads, at most once per refresh
     * period and query; a failure never blocks the run.
     */
    private function validate(Subject $subject): void
    {
        $fresh = ($subject->keyword_metrics['query'] ?? null) === $subject->query
            && $subject->keyword_metrics_on?->gt(now()->subDays(config('trend.google_ads.refresh_after_days')));
        $source = Source::where('key', 'google_ads')->where('enabled', true)->first();

        if ($fresh || $source === null || ! $this->googleAds->configured()) {
            return;
        }

        try {
            $metrics = $this->googleAds->metrics($subject->query);
            $subject->update(['keyword_metrics' => $metrics === null ? null : ['query' => $subject->query, ...$metrics], 'keyword_metrics_on' => now()->toDateString()]);
            $source->update(['last_success_at' => now(), 'last_error' => null]);
        } catch (Throwable $e) {
            $source->update(['last_error' => Str::limit($e->getMessage(), 500)]);
        }
    }

    /** Days from when the Subject last entered Watching to the end of the scored week; never negative. */
    private function daysWatching(Subject $subject, CarbonImmutable $week): int
    {
        $since = $subject->events()->where('to_state', SubjectState::Watching->value)->max('happened_at')
            ?? $subject->first_seen_on;

        return max(0, (int) CarbonImmutable::parse($since)->diffInDays($week->addWeek(), absolute: false));
    }
}
