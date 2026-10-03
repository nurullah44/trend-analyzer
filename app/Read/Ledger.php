<?php

namespace App\Read;

use App\Models\Alarm;
use App\Models\Event;
use App\Models\Item;
use App\Models\Source;
use App\Models\Subject;
use App\Trends\Scorer;
use App\Trends\Series;
use App\Trends\WeeklyRun;
use Carbon\CarbonImmutable;

/**
 * The one read layer: everything the CLI, the MCP server and the pages show
 * comes from here, as plain arrays, so the three doors cannot drift apart.
 */
final class Ledger
{
    public function __construct(
        private readonly Series $series,
        private readonly Scorer $scorer,
    ) {}

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

    /**
     * Subjects, newest first, optionally narrowed to a state or a Label.
     *
     * @return list<array<string, mixed>>
     */
    public function subjects(?string $state = null, ?string $label = null): array
    {
        return Subject::with('labels')
            ->when($state, fn ($query) => $query->where('state', $state))
            ->when($label, fn ($query) => $query->whereRelation('labels', 'name', $label))
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Subject $subject) => $this->summary($subject))
            ->all();
    }

    /**
     * One Subject with everything behind it: its weekly series, its latest score
     * worked out from that series, every Event, its Alarms, keyword metrics and
     * the recent Items that mention it. Null when no Subject has that slug.
     *
     * @return array<string, mixed>|null
     */
    public function subject(string $slug): ?array
    {
        $subject = Subject::with(['labels', 'alarms', 'events'])->where('slug', $slug)->first();

        if ($subject === null) {
            return null;
        }

        $series = $this->series->of($subject);

        return [
            ...$this->summary($subject),
            'magnitude' => $subject->magnitude?->value,
            'series' => $series,
            'score' => $subject->scored_week === null ? null : $this->scorer->score($series, $subject->scored_week)->toArray(),
            'keyword_metrics' => $subject->keyword_metrics,
            'events' => $subject->events->sortBy('id')->values()->map(fn (Event $event) => [
                'type' => $event->type,
                'from' => $event->from_state,
                'to' => $event->to_state,
                'reason' => $event->reason,
                'numbers' => $event->payload,
                'at' => $event->happened_at->toIso8601String(),
            ])->all(),
            'alarms' => $subject->alarms->sortByDesc('week')->values()->map(fn (Alarm $alarm) => $this->alarm($alarm->setRelation('subject', $subject)))->all(),
            'items' => $this->mentions($subject),
        ];
    }

    /**
     * Alarms, newest week first: one week's, or every open one.
     *
     * @return list<array<string, mixed>>
     */
    public function alarms(?string $week = null): array
    {
        return Alarm::with('subject.labels')
            ->when($week, fn ($query) => $query->where('week', $week), fn ($query) => $query->whereNull('closed_on'))
            ->orderByDesc('week')
            ->orderByDesc('corroboration')
            ->orderByDesc('score')
            ->get()
            ->map(fn (Alarm $alarm) => $this->alarm($alarm))
            ->all();
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
    private function summary(Subject $subject): array
    {
        return [
            'name' => $subject->name,
            'slug' => $subject->slug,
            'query' => $subject->query,
            'state' => $subject->state->value,
            'labels' => $subject->labels->pluck('name')->all(),
            'first_seen_on' => $subject->first_seen_on->toDateString(),
            'scored_week' => $subject->scored_week,
            'mainstream_on' => $subject->mainstream_on?->toDateString(),
            'lead_time_days' => $subject->lead_time_days,
        ];
    }

    /**
     * The last fortnight's collected Items that mention the Subject's query.
     *
     * @return list<array<string, mixed>>
     */
    private function mentions(Subject $subject): array
    {
        return Item::with('source')
            ->where('observed_on', '>=', now('UTC')->subDays(14)->toDateString())
            ->mentioning($subject->query)
            ->orderByDesc('observed_on')
            ->limit(20)
            ->get()
            ->map(fn (Item $item) => [
                'source' => $item->source->key,
                'title' => $item->title,
                'url' => $item->url,
                'observed_on' => $item->observed_on->toDateString(),
                'measured_quantity' => $item->measured_quantity,
            ])
            ->all();
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
