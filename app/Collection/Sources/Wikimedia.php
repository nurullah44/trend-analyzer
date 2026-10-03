<?php

namespace App\Collection\Sources;

use App\Collection\SourceHttp;
use App\Collection\SourceMeasurement;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use UnexpectedValueException;

/**
 * Daily views of the English Wikipedia article named by the query, summed over
 * one week. A Subject without an article has no series here, which is not an
 * error. Redirects are not followed: the query must be the article's title.
 */
final class Wikimedia implements SourceMeasurement
{
    public function __construct(private readonly Factory $http) {}

    public function key(): string
    {
        return 'wikimedia';
    }

    public function volume(string $query, CarbonImmutable $week): ?int
    {
        $article = rawurlencode(str_replace(' ', '_', ucfirst(trim($query))));
        $from = $week->format('Ymd');
        $to = $week->addDays(6)->format('Ymd');

        $response = SourceHttp::client($this->http, 'https://wikimedia.org')
            ->get("/api/rest_v1/metrics/pageviews/per-article/en.wikipedia/all-access/user/{$article}/daily/{$from}/{$to}");

        if ($response->notFound()) {
            return null;
        }

        $items = $response->throw()->json('items');

        if (! is_array($items)) {
            throw new UnexpectedValueException("Wikimedia answered without items for [{$query}].");
        }

        return array_sum(array_column($items, 'views'));
    }
}
