<?php

namespace App\Trends;

use App\Collection\SourceMeasurement;
use App\Collection\SourceRegistry;
use App\Collection\Sources\GoogleAds;
use App\Enums\SubjectState;
use App\Models\Source;
use App\Models\Subject;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Runs the weekly score backwards over history for known cases, with the same
 * enabled measurement Sources, Scorer, States and Mainstream markers as the
 * weekly run. A case is replayed as if it had been seeded at the start, so the
 * thirty-day archival never ends it early: the question is whether the score
 * would have caught the rise, not whether discovery would have found it. It
 * holds everything in memory and writes nothing.
 */
final class Backtest
{
    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly Scorer $scorer,
        private readonly States $states,
        private readonly Mainstream $mainstream,
        private readonly GoogleAds $googleAds,
    ) {}

    /**
     * @param  list<string>|null  $only  measurement Source keys to use; all of them when null
     * @return array{query: string, rising: ?string, trending: ?string, mainstream: ?string, lead_weeks: ?int, failure: ?string}
     */
    public function replay(string $query, CarbonImmutable $from, CarbonImmutable $to, ?array $only = null): array
    {
        $result = ['query' => $query, 'rising' => null, 'trending' => null, 'mainstream' => null, 'lead_weeks' => null, 'failure' => null];
        $baseline = config('trend.scoring.baseline_weeks');

        try {
            $series = $this->measure($query, $from->subWeeks($baseline), $to, $only);
            $subject = new Subject(['query' => $query, 'keyword_metrics' => $this->keywordMetrics($query)]);
        } catch (Throwable $e) {
            return ['failure' => $e->getMessage()] + $result;
        }

        $state = SubjectState::Watching;

        for ($week = $from; $week <= $to; $week = $week->addWeek()) {
            $day = $week->toDateString();

            if ($this->mainstream->arrived($subject, $series, $week) !== null) {
                $result['mainstream'] = $day;

                break;
            }

            $next = $this->states->next($state, $this->scorer->score($series, $day), 0);

            if ($next !== null) {
                $state = $next[0];
                $result['rising'] ??= $state === SubjectState::Rising || $state === SubjectState::Trending ? $day : null;
                $result['trending'] ??= $state === SubjectState::Trending ? $day : null;
            }
        }

        if ($result['trending'] !== null && $result['mainstream'] !== null) {
            $result['lead_weeks'] = (int) CarbonImmutable::parse($result['trending'])->diffInWeeks(CarbonImmutable::parse($result['mainstream']));
        }

        return $result;
    }

    /**
     * Google Ads' twelve months, so its marker can fire for the past year as it would in the weekly run.
     *
     * @return array<string, mixed>|null
     */
    private function keywordMetrics(string $query): ?array
    {
        if (! $this->googleAds->configured() || ! Source::where('key', 'google_ads')->where('enabled', true)->exists()) {
            return null;
        }

        $metrics = $this->googleAds->metrics($query);

        return $metrics === null ? null : ['query' => $query, ...$metrics];
    }

    /**
     * @param  list<string>|null  $only
     * @return array<string, array<string, ?int>>
     */
    private function measure(string $query, CarbonImmutable $from, CarbonImmutable $to, ?array $only): array
    {
        $enabled = Source::where('enabled', true)->pluck('key')->all();
        $measurements = array_filter(
            $this->sources->measurements(),
            fn (SourceMeasurement $source, string $key) => in_array($key, $enabled, true) && ($only === null || in_array($key, $only, true)),
            ARRAY_FILTER_USE_BOTH,
        );
        $weeks = [];

        for ($week = $from; $week <= $to; $week = $week->addWeek()) {
            $weeks[] = $week->toDateString();
        }

        return array_map(fn (SourceMeasurement $measurement) => $measurement->volumes($query, $weeks), $measurements);
    }
}
