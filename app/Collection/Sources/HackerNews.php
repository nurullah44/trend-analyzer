<?php

namespace App\Collection\Sources;

use App\Collection\CollectedDay;
use App\Collection\CollectedItem;
use App\Collection\SourceCollector;
use App\Collection\SourceHttp;
use App\Collection\SourceMeasurement;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use UnexpectedValueException;

/**
 * Hacker News stories through the Algolia API. Discovery reads every story of a
 * UTC day, points as the measured quantity; measurement counts the stories
 * matching a quoted query in one week.
 *
 * Algolia returns at most 1,000 hits per query, so a day is read in four
 * six-hour windows (a busy window is a few hundred stories) and a window that
 * would overflow fails the run rather than silently losing stories.
 */
final class HackerNews implements SourceCollector, SourceMeasurement
{
    private const WINDOW_HOURS = 6;

    private const MAX_HITS = 1000;

    public function __construct(private readonly Factory $http) {}

    public function key(): string
    {
        return 'hacker_news';
    }

    public function collectForDay(Source $source, CarbonImmutable $day): CollectedDay
    {
        $from = $day->utc()->startOfDay();
        $items = [];

        for ($start = $from; $start < $from->addDay(); $start = $start->addHours(self::WINDOW_HOURS)) {
            $payload = $this->search('search_by_date', [
                'tags' => 'story',
                'numericFilters' => $this->between($start, $start->addHours(self::WINDOW_HOURS)),
                'hitsPerPage' => self::MAX_HITS,
                'attributesToRetrieve' => 'title,url,points,created_at_i',
                'attributesToHighlight' => '',
            ]);

            if ($payload['nbHits'] > count($payload['hits'])) {
                throw new UnexpectedValueException("Hacker News had more than {$payload['nbHits']} stories in one window from {$start->toIso8601String()}.");
            }

            foreach ($payload['hits'] as $hit) {
                $items[] = new CollectedItem(
                    externalId: (string) $hit['objectID'],
                    title: (string) $hit['title'],
                    url: $hit['url'] ?? 'https://news.ycombinator.com/item?id='.$hit['objectID'],
                    publishedAt: CarbonImmutable::createFromTimestampUTC((int) $hit['created_at_i']),
                    measuredQuantity: (int) ($hit['points'] ?? 0),
                );
            }
        }

        return new CollectedDay($from, $items);
    }

    public function volume(string $query, CarbonImmutable $week): int
    {
        return $this->search('search', [
            'query' => '"'.str_replace('"', '', $query).'"',
            'tags' => 'story',
            'numericFilters' => $this->between($week, $week->addWeek()),
            'hitsPerPage' => 0,
        ])['nbHits'];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{hits: list<array<string, mixed>>, nbHits: int}
     */
    private function search(string $endpoint, array $query): array
    {
        $payload = $this->client()->get("/api/v1/{$endpoint}", $query)->throw()->json();

        if (! is_array($payload) || ! is_array($payload['hits'] ?? null) || ! is_int($payload['nbHits'] ?? null)) {
            throw new UnexpectedValueException('Hacker News answered without hits.');
        }

        return $payload;
    }

    private function between(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return "created_at_i>={$from->getTimestamp()},created_at_i<{$to->getTimestamp()}";
    }

    private function client(): PendingRequest
    {
        return SourceHttp::client($this->http, 'https://hn.algolia.com');
    }
}
