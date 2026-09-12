<?php

namespace App\Collection\Sources;

use App\Collection\CollectedDay;
use App\Collection\CollectedItem;
use App\Collection\SourceCollector;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * Stack Exchange questions created on a UTC day — people asking for help, which
 * is as close to pure demand as a public API gets. The question's tags ride
 * along as the excerpt and its score is the measured quantity.
 *
 * The anonymous quota is 300 requests a day and a normal day is a handful of
 * pages of 100. A key can be added later; nothing here needs one.
 */
final class StackExchange implements SourceCollector
{
    private const KEY = 'stack_exchange';

    private const SITE = 'stackoverflow';

    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 100;

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
        $response = $this->http
            ->baseUrl('https://api.stackexchange.com')
            ->acceptJson()
            ->withUserAgent('trend-analyzer/0.1')
            ->retry(2, 500, when: fn (Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)))
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

    /** @param array<string, mixed> $question */
    private function toItem(array $question): CollectedItem
    {
        return new CollectedItem(
            externalId: (string) $question['question_id'],
            title: (string) $question['title'],
            excerpt: implode(', ', $question['tags'] ?? []),
            url: $question['link'] ?? null,
            publishedAt: CarbonImmutable::createFromTimestampUTC((int) $question['creation_date']),
            signal: (int) ($question['score'] ?? 0),
        );
    }

    /** Stack Exchange asks clients to wait when it sends a backoff; never for longer than a minute. */
    private function respectBackoff(mixed $backoff): void
    {
        $seconds = (int) $backoff;

        if ($seconds > 0) {
            sleep(min($seconds, 60));
        }
    }
}
