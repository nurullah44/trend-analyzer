<?php

namespace App\Trends;

use Carbon\CarbonImmutable;

/**
 * Velocity, Corroboration and the Trend Score as pure functions over a weekly
 * series (ADR-0004). The thresholds are a deliberately crude placeholder until
 * Verdicts and the backtest tune them.
 */
final class Scorer
{
    /**
     * @param  array<string, array<string, ?int>>  $series  Source key => week (Y-m-d Monday) => Volume
     */
    public function score(array $series, string $week): Score
    {
        $volumes = $velocities = $rising = [];
        $config = config('trend.scoring');

        foreach ($series as $source => $weeks) {
            $volume = $weeks[$week] ?? null;

            if ($volume === null) {
                continue;
            }

            $volumes[$source] = $volume;
            $velocity = $this->velocity($volume, $this->baseline($weeks, $week));

            if ($velocity === null) {
                continue;
            }

            $velocities[$source] = $velocity;

            if ($velocity >= $config['rising_velocity'] && $volume >= $config['min_volume']) {
                $rising[] = $source;
            }
        }

        $trendScore = array_sum(array_map(
            fn (float $velocity) => min(max($velocity, 0), $config['velocity_cap']),
            $velocities,
        ));

        return new Score($volumes, $velocities, $rising, $trendScore);
    }

    /**
     * How many spreads this week's Volume sits above the baseline's median. The
     * spread never falls below the Poisson floor √median, or 1, so a quiet
     * baseline cannot turn one extra mention into a huge Velocity. Null on a cold
     * start: too few baseline weeks to judge.
     *
     * @param  list<int>  $baseline
     */
    private function velocity(int $volume, array $baseline): ?float
    {
        if (count($baseline) < config('trend.scoring.min_baseline_weeks')) {
            return null;
        }

        $median = $this->median($baseline);
        $mad = $this->median(array_map(fn (int $value) => abs($value - $median), $baseline));
        $spread = max(1.4826 * $mad, sqrt($median), 1.0);

        return ($volume - $median) / $spread;
    }

    /**
     * The measured Volumes of the baseline weeks right before this one. A week the
     * Source could not measure is missing, never replaced by an older one.
     *
     * @param  array<string, ?int>  $weeks
     * @return list<int>
     */
    private function baseline(array $weeks, string $week): array
    {
        $monday = CarbonImmutable::parse($week, 'UTC');

        return array_values(array_filter(array_map(
            fn (int $back) => $weeks[$monday->subWeeks($back)->toDateString()] ?? null,
            range(1, config('trend.scoring.baseline_weeks')),
        ), fn (?int $volume) => $volume !== null));
    }

    /** @param list<int|float> $values */
    private function median(array $values): float
    {
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
