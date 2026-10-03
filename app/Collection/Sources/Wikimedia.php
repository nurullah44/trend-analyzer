<?php

namespace App\Collection\Sources;

use App\Collection\SourceHttp;
use App\Collection\SourceMeasurement;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use UnexpectedValueException;

/**
 * Daily views of the English Wikipedia article named by the query, summed per
 * week, every requested week in one request. A Subject without an article has no series here, which is not an
 * error. Redirects are not followed: the query must be the article's title.
 */
final class Wikimedia implements SourceMeasurement
{
    public function __construct(private readonly Factory $http) {}

    public function key(): string
    {
        return 'wikimedia';
    }

    public function volumes(string $query, array $weeks): array
    {
        if ($weeks === []) {
            return [];
        }

        sort($weeks);
        $article = rawurlencode(str_replace(' ', '_', ucfirst(trim($query))));
        $from = CarbonImmutable::parse($weeks[0], 'UTC')->format('Ymd');
        $to = CarbonImmutable::parse(end($weeks), 'UTC')->addDays(6)->format('Ymd');

        // One request for the whole range: the API rate-limits clients that ask week by week.
        $response = SourceHttp::client($this->http, 'https://wikimedia.org')
            ->get("/api/rest_v1/metrics/pageviews/per-article/en.wikipedia/all-access/user/{$article}/daily/{$from}/{$to}");

        if ($response->notFound()) {
            return array_fill_keys($weeks, null);
        }

        $items = $response->throw()->json('items');

        if (! is_array($items)) {
            throw new UnexpectedValueException("Wikimedia answered without items for [{$query}].");
        }

        $volumes = array_fill_keys($weeks, 0);

        foreach ($items as $item) {
            $week = CarbonImmutable::createFromFormat('YmdH', (string) $item['timestamp'], 'UTC')->startOfWeek()->toDateString();

            if (array_key_exists($week, $volumes)) {
                $volumes[$week] += (int) $item['views'];
            }
        }

        return $volumes;
    }
}
