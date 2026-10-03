<?php

namespace App\Read;

use App\Models\Alarm;
use App\Models\Item;
use App\Models\Source;
use App\Models\Subject;
use App\Trends\WeeklyRun;
use Carbon\CarbonImmutable;

/**
 * The one read layer: everything the CLI, the MCP server and the pages show
 * comes from here, as plain arrays, so the three doors cannot drift apart.
 */
final class Ledger
{
    /** The last finished week's Monday, the week a report is about by default. */
    public static function lastWeek(): string
    {
        return CarbonImmutable::now('UTC')->startOfWeek()->subWeek()->toDateString();
    }

    /**
     * The weekly report: the week's Alarms ranked by Corroboration then Trend
     * Score, at most twenty, with Source health and the analyzer's gaps.
     *
     * @return array<string, mixed>
     */
    public function report(?string $week = null): array
    {
        $week ??= self::lastWeek();

        return [
            'week' => $week,
            'alarms' => Alarm::with('subject.labels')
                ->where('week', $week)
                ->orderByDesc('corroboration')
                ->orderByDesc('score')
                ->limit(20)
                ->get()
                ->map(fn (Alarm $alarm) => $this->alarm($alarm))
                ->all(),
            'sources' => $this->sources(),
            'gaps' => $this->gaps(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function sources(): array
    {
        return Source::orderBy('key')->get()->map(fn (Source $source) => [
            'key' => $source->key,
            'enabled' => $source->enabled,
            'roles' => explode(',', $source->roles),
            'last_success_at' => $source->last_success_at?->toIso8601String(),
            'last_item_count' => $source->last_item_count,
            'last_error' => $source->last_error,
            'note' => $source->cost_note,
        ])->all();
    }

    /** @return list<string> what the analyzer could not see or do, in plain words */
    public function gaps(): array
    {
        $gaps = [];

        foreach (Source::where('enabled', true)->whereNotNull('last_error')->orderBy('key')->get() as $source) {
            $gaps[] = "{$source->key} is failing: {$source->last_error}";
        }

        foreach (Source::where('enabled', true)->where('roles', 'like', '%discovery%')->orderBy('key')->get() as $source) {
            if (! Item::where('source_id', $source->id)->where('observed_on', '>=', now('UTC')->subDays(2)->toDateString())->exists()) {
                $gaps[] = "{$source->key} has collected nothing in the last two days";
            }
        }

        if (blank(config('trend.classifier.key'))) {
            $gaps[] = 'the Classifier has no key: every Candidate waits in Backlog';
        }

        $blind = Subject::whereIn('state', WeeklyRun::TRACKED)
            ->whereHas('weeks')
            ->whereDoesntHave('weeks', fn ($query) => $query->where('volume', '>', 0))
            ->pluck('name');

        if ($blind->isNotEmpty()) {
            $gaps[] = 'no Source finds anything for: '.$blind->join(', ');
        }

        return $gaps;
    }

    /** @return array<string, mixed> */
    private function alarm(Alarm $alarm): array
    {
        return [
            'id' => $alarm->id,
            'subject' => $alarm->subject->name,
            'slug' => $alarm->subject->slug,
            'state' => $alarm->subject->state->value,
            'labels' => $alarm->subject->labels->pluck('name')->all(),
            'week' => $alarm->week,
            'corroboration' => $alarm->corroboration,
            'trend_score' => round($alarm->score, 2),
            'verdict' => $alarm->verdict?->value,
            'verdict_note' => $alarm->verdict_note,
            'closed_on' => $alarm->closed_on?->toDateString(),
            'evidence' => $alarm->evidence,
        ];
    }
}
