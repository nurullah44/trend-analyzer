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
use RuntimeException;
use UnexpectedValueException;

/**
 * Stack Exchange questions created on a UTC day — people asking for help, which
 * is as close to pure demand as a public API gets. The question's tags ride
 * along as the excerpt and its score is the measured quantity. Measurement is
 * the number of questions matching a query in one week.
 *
 * The anonymous quota is 300 requests a day and a normal day is a handful of
 * pages of 100. A key can be added later; nothing here needs one.
 */
final class StackExchange implements SourceCollector, SourceMeasurement
{
    private const KEY = 'stack_exchange';

    private const SITE = 'stackoverflow';

    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 100;

    /** A filter created once through /filters/create: just .total, .backoff and .quota_remaining. Filters never change. */
    private const TOTAL_FILTER = '!9n30IGbb1J()';

    public function __construct(private readonly Factory $http) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function collectForDay(Source $source, CarbonImmutable $day): CollectedDay
    {
        $from = $day->utc()->startOfDay();
        $to = $from->addDay()->subSecond();

        $items = [];

        for ($page = 1; ; $page++) {
            $payload = $this->fetchPage($from, $to, $page);

            foreach ($payload['items'] as $question) {
                $items[] = $this->toItem($question);
            }

            if (! (bool) ($payload['has_more'] ?? false)) {
                break;
            }

            if ($page >= self::MAX_PAGES) {
                throw new RuntimeException(
                    'Stack Exchange still had more pages after '.self::MAX_PAGES.' for '.$from->toDateString().'.'
                );
            }

            $this->respectBackoff($payload['backoff'] ?? 0);
        }

        return new CollectedDay($from, $items);
    }

    /**
     * @return array{items: list<array<string, mixed>>, has_more?: bool, backoff?: int}
     */
    private function fetchPage(CarbonImmutable $from, CarbonImmutable $to, int $page): array
    {
        $response = SourceHttp::client($this->http, 'https://api.stackexchange.com')
            ->get('/2.3/questions', [
                'site' => self::SITE,
                'fromdate' => $from->getTimestamp(),
                'todate' => $to->getTimestamp(),
                'order' => 'desc',
                'sort' => 'creation',
                'pagesize' => self::PAGE_SIZE,
                'page' => $page,
            ]);

        $response->throw();

        $payload = $response->json();

        // An answer we cannot read must fail the run: a broken response is not a day
        // with no questions, and it must never look like one in the Source's health.
        if (! is_array($payload) || ! is_array($payload['items'] ?? null)) {
            throw new UnexpectedValueException(
                'Stack Exchange answered without items for '.$from->toDateString().'.'
            );
        }

        return $payload;
    }

    public function volume(string $query, CarbonImmutable $week): int
    {
        $payload = SourceHttp::client($this->http, 'https://api.stackexchange.com')
            ->get('/2.3/search/advanced', [
                'site' => self::SITE,
                'q' => $query,
                'fromdate' => $week->getTimestamp(),
                'todate' => $week->addWeek()->getTimestamp() - 1,
                'filter' => self::TOTAL_FILTER,
            ])
            ->throw()
            ->json();

        if (! is_int($payload['total'] ?? null)) {
            throw new UnexpectedValueException("Stack Exchange answered without a total for [{$query}].");
        }

        // Measurement runs many searches back to back; the next one must wait if asked to.
        $this->respectBackoff($payload['backoff'] ?? 0);

        return $payload['total'];
    }

    /** @param array<string, mixed> $question */
    private function toItem(array $question): CollectedItem
    {
        return new CollectedItem(
            externalId: (string) $question['question_id'],
            title: (string) $question['title'],
            excerpt: implode(', ', $question['tags'] ?? []),
            url: $question['link'] ?? null,
            publishedAt: CarbonImmutable::createFromTimestampUTC((int) $question['creation_date']),
            measuredQuantity: (int) ($question['score'] ?? 0),
        );
    }

    /** Stack Exchange asks clients to wait when it sends a backoff; this is a daily batch, so waiting is safe. */
    private function respectBackoff(mixed $backoff): void
    {
        $seconds = (int) $backoff;

        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
