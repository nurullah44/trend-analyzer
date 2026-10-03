<?php

namespace App\Trends;

use App\Models\Subject;
use Carbon\CarbonImmutable;

/**
 * The Mainstream markers (ADR-0005): one of the Subject's own measures over an
 * absolute line, and holding — two consecutive weeks of Wikipedia views, or the
 * two full months of Google Ads searches before the scored week. A single
 * crossing is not arrival.
 */
final class Mainstream
{
    /**
     * @param  array<string, array<string, ?int>>  $series
     * @return string|null why the Subject has arrived, or null when it has not
     */
    public function arrived(Subject $subject, array $series, CarbonImmutable $week): ?string
    {
        $config = config('trend.mainstream');
        $views = $series['wikimedia'] ?? [];
        $thisWeek = $views[$week->toDateString()] ?? null;
        $lastWeek = $views[$week->subWeek()->toDateString()] ?? null;

        if ($thisWeek !== null && $lastWeek !== null && min($thisWeek, $lastWeek) >= $config['wikipedia_weekly_views']) {
            return "Wikipedia views held over {$config['wikipedia_weekly_views']} a week ({$lastWeek}, {$thisWeek})";
        }

        // The two full months before the scored week, and only figures for the Subject's current query.
        $metrics = ($subject->keyword_metrics['query'] ?? null) === $subject->query ? $subject->keyword_metrics : [];
        $months = array_map(
            fn (int $back) => $metrics['monthly'][$week->startOfMonth()->subMonthsNoOverflow($back)->format('Y-m')] ?? null,
            [2, 1],
        );

        if (! in_array(null, $months, true) && min($months) >= $config['monthly_searches']) {
            return "Google searches held over {$config['monthly_searches']} a month (".implode(', ', $months).')';
        }

        return null;
    }
}
