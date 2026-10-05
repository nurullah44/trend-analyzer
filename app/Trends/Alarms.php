<?php

namespace App\Trends;

use App\Enums\SubjectState;
use App\Models\Alarm;
use App\Models\Item;
use App\Models\Subject;
use Carbon\CarbonImmutable;

/**
 * Publishes an Alarm when a Subject enters Trending. An Alarm carries Evidence
 * only — the numbers, the series, the Items and links anyone can check — and
 * never generated prose.
 */
final class Alarms
{
    /** @param array<string, array<string, ?int>> $series */
    public function publish(Subject $subject, Score $score, array $series, CarbonImmutable $week): Alarm
    {
        return Alarm::updateOrCreate(['subject_id' => $subject->id, 'week' => $week->toDateString()], [
            'published_on' => now()->toDateString(),
            'state_at_publication' => SubjectState::Trending->value,
            'volume' => array_sum($score->volumes),
            'velocity' => $score->velocities === [] ? 0 : max($score->velocities),
            'corroboration' => $score->corroboration(),
            'score' => $score->trendScore,
            'evidence' => [
                'query' => $subject->query,
                'week' => $week->toDateString(),
                ...$score->toArray(),
                'series' => $series,
                'items' => $this->items($subject, $week),
                'links' => $this->links($subject->query),
                'keyword_metrics' => $subject->keyword_metrics,
                'app_competition' => $subject->app_competition,
            ],
        ]);
    }

    /**
     * The scored week's collected Items that mention the query, strongest first.
     *
     * @return list<array<string, mixed>>
     */
    private function items(Subject $subject, CarbonImmutable $week): array
    {
        return Item::with('source')
            ->whereBetween('observed_on', [$week->toDateString(), $week->addDays(6)->toDateString()])
            ->mentioning($subject->query)
            ->orderByDesc('measured_quantity')
            ->limit(10)
            ->get()
            ->map(fn (Item $item) => [
                'source' => $item->source->key,
                'title' => $item->title,
                'url' => $item->url,
                'published_at' => $item->published_at?->toIso8601String(),
                'measured_quantity' => $item->measured_quantity,
            ])
            ->all();
    }

    /** @return array<string, string> where each Source's number can be checked by hand */
    private function links(string $query): array
    {
        return [
            'hacker_news' => 'https://hn.algolia.com/?type=story&query='.rawurlencode('"'.$query.'"'),
            'stack_exchange' => 'https://stackoverflow.com/search?q='.rawurlencode($query),
            'wikimedia' => 'https://pageviews.wmcloud.org/?project=en.wikipedia.org&pages='.rawurlencode(str_replace(' ', '_', ucfirst($query))),
        ];
    }
}
