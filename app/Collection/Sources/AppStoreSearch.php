<?php

namespace App\Collection\Sources;

use App\Collection\SourceHttp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use UnexpectedValueException;

/**
 * Competition (ADR-0011): the apps App Store search returns for a query, in
 * store order, from Apple's iTunes Search API. Only metadata and the store link
 * are kept — Apple's terms reserve artwork and previews for promoting the
 * store. Evidence for the reviewer; nothing here is scored.
 *
 * Apple allows about twenty calls a minute, so calls are spaced three seconds
 * apart, and a query's answer is reused for the refresh period.
 */
final class AppStoreSearch
{
    private const SPACING_SECONDS = 3;

    private ?float $lastCall = null;

    public function __construct(private readonly Factory $http) {}

    /**
     * @return array{source: string, observed_on: string, storefront: string, result_count: int, apps: list<array{name: string, seller: ?string, url: ?string, genre: ?string, ratings: int, rating: ?float, released: ?string, updated: ?string, price: ?string}>}
     */
    public function competition(string $query): array
    {
        $storefront = config('trend.app_store_search.storefront');
        $key = 'app_store_search.'.$storefront.'.'.md5(mb_strtolower(trim($query)));

        return Cache::remember($key, now()->addDays(config('trend.app_store_search.refresh_after_days')), fn () => $this->search($query, $storefront));
    }

    /** @return array{source: string, observed_on: string, storefront: string, result_count: int, apps: list<array{name: string, seller: ?string, url: ?string, genre: ?string, ratings: int, rating: ?float, released: ?string, updated: ?string, price: ?string}>} */
    private function search(string $query, string $storefront): array
    {
        $this->pace();

        $payload = SourceHttp::client($this->http, 'https://itunes.apple.com')
            ->get('/search', [
                'term' => trim($query),
                'country' => $storefront,
                'media' => 'software',
                'entity' => 'software',
                'limit' => config('trend.app_store_search.top_apps'),
            ])
            ->throw()
            ->json();

        if (! is_array($payload) || ! is_array($payload['results'] ?? null)) {
            throw new UnexpectedValueException("App Store search answered without results for [{$query}].");
        }

        return [
            'source' => 'app_store_search',
            'observed_on' => CarbonImmutable::now('UTC')->toDateString(),
            'storefront' => $storefront,
            'result_count' => (int) ($payload['resultCount'] ?? count($payload['results'])),
            'apps' => array_map(fn (array $app) => [
                'name' => (string) ($app['trackName'] ?? ''),
                'seller' => $app['sellerName'] ?? null,
                'url' => $app['trackViewUrl'] ?? null,
                'genre' => $app['primaryGenreName'] ?? null,
                'ratings' => (int) ($app['userRatingCount'] ?? 0),
                'rating' => isset($app['averageUserRating']) ? round((float) $app['averageUserRating'], 2) : null,
                'released' => $this->date($app['releaseDate'] ?? null),
                'updated' => $this->date($app['currentVersionReleaseDate'] ?? null),
                'price' => $app['formattedPrice'] ?? null,
            ], array_values($payload['results'])),
        ];
    }

    private function pace(): void
    {
        $since = $this->lastCall === null ? null : microtime(true) - $this->lastCall;

        if ($since !== null && $since < self::SPACING_SECONDS) {
            Sleep::usleep((int) ((self::SPACING_SECONDS - $since) * 1_000_000));
        }

        $this->lastCall = microtime(true);
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->utc()->toDateString() : null;
    }
}
