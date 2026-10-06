<?php

namespace App\Trends;

use Carbon\CarbonImmutable;

/**
 * Velocity, Sustained growth, Corroboration and the Trend Score as pure
 * functions over a weekly series (ADR-0004, ADR-0012). The thresholds are a deliberately crude placeholder until
 * Verdicts and the backtest tune them.
 */
final class Scorer
{
    /**
     * @param  array<string, array<string, ?int>>  $series  Source key => week (Y-m-d Monday) => Volume
     */
    public function score(array $series, string $week): Score
    {
        $volumes = $velocities = $rising = $sustained = $seasonal = [];
        $config = config('trend.scoring');

        foreach ($series as $source => $weeks) {
            $volume = $weeks[$week] ?? null;

            if ($volume === null) {
                continue;
            }

            $volumes[$source] = $volume;
            $velocity = $this->velocity($volume, $this->baseline($weeks, $week));

            if ($velocity !== null) {
                $velocities[$source] = $velocity;
            }

            // On a Source read for Sustained growth a one-week jump never counts: only growth that held does.
            if (in_array($source, $config['growth']['sources'], true)) {
                $growth = $this->growth($weeks, $week);

                if ($growth !== null) {
                    $rising[] = $source;
                    $growth === 'seasonal' ? $seasonal[] = $source : $sustained[] = $source;
                }

                continue;
            }

            if ($velocity !== null && $velocity >= $config['rising_velocity'] && $volume >= $config['min_volume']) {
                $rising[] = $source;
            }
        }

        $trendScore = array_sum(array_map(
            fn (float $velocity) => min(max($velocity, 0), $config['velocity_cap']),
            $velocities,
        ));

        return new Score($volumes, $velocities, $rising, $trendScore, $sustained, $seasonal);
    }

    /** How many weeks before the scored one a Source's series must reach. */
    public static function lookback(string $source): int
    {
        $config = config('trend.scoring');

        return in_array($source, $config['growth']['sources'], true)
            ? max($config['baseline_weeks'], $config['growth']['history_weeks'])
            : $config['baseline_weeks'];
    }

    /**
     * Sustained growth (ADR-0012): ranked this week and in most of the recent
     * weeks, their median above the weeks before, and — unless it is Seasonal —
     * above its best week around the same time a year earlier. A week below the
     * Source's list is ordered below every ranked week and never averaged as a
     * number: an even median takes the lower middle for now and the upper middle
     * for before. A week never measured or no longer kept makes the answer
     * unknown, never growth.
     *
     * @param  array<string, ?int>  $weeks
     * @return 'sustained'|'seasonal'|null
     */
    private function growth(array $weeks, string $week): ?string
    {
        $config = config('trend.scoring.growth');
        $monday = CarbonImmutable::parse($week, 'UTC');
        $measured = fn (array $backs) => array_values(array_map(
            fn (string $day) => $weeks[$day],
            array_filter(array_map(fn (int $back) => $monday->subWeeks($back)->toDateString(), $backs), fn (string $day) => array_key_exists($day, $weeks)),
        ));

        $recent = $measured(range(0, $config['recent_weeks'] - 1));
        $prior = $measured(range($config['recent_weeks'], $config['recent_weeks'] + $config['prior_weeks'] - 1));

        if (count($recent) < $config['recent_weeks'] || count(array_filter($recent, fn (?int $volume) => $volume !== null)) < $config['held_weeks']
            || count($prior) < $config['prior_weeks']) {
            return null;
        }

        $now = $this->censoredMedian($recent, upper: false);

        if ($now === null || ! $this->clears($now, $this->censoredMedian($prior, upper: true), $config['min_growth'])) {
            return null;
        }

        $window = range(52 - $config['year_ago_weeks'], 52 + $config['year_ago_weeks']);
        $yearAgo = $measured($window);

        // Without the whole year-ago window it cannot be told apart from a season.
        if (count($yearAgo) < count($window)) {
            return 'seasonal';
        }

        $ranked = array_filter($yearAgo, fn (?int $volume) => $volume !== null);

        return $this->clears($now, $ranked === [] ? null : max($ranked), $config['min_growth']) ? 'sustained' : 'seasonal';
    }

    /** Whether a ranked level clears another by the margin; anything clears a week below the list. */
    private function clears(int $level, ?int $other, int $margin): bool
    {
        return $other === null || $level >= $other + $margin;
    }

    /**
     * The middle of weeks where below the list (null) sorts under every ranked
     * week; for an even count the lower or the upper of the two middles.
     *
     * @param  list<?int>  $weeks
     */
    private function censoredMedian(array $weeks, bool $upper): ?int
    {
        usort($weeks, fn (?int $a, ?int $b) => ($a ?? PHP_INT_MIN) <=> ($b ?? PHP_INT_MIN));
        $count = count($weeks);

        return $weeks[$count % 2 ? intdiv($count, 2) : intdiv($count, 2) - ($upper ? 0 : 1)];
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
