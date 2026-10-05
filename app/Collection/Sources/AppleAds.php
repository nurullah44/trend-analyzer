<?php

namespace App\Collection\Sources;

use App\Collection\CollectedDay;
use App\Collection\CollectedItem;
use App\Collection\NeedsCredentials;
use App\Collection\PublishesWeekly;
use App\Collection\SourceCollector;
use App\Collection\SourceHttp;
use App\Collection\SourceMeasurement;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * Apple Ads search-term popularity (ADR-0011): what people type into App Store
 * search, ranked per genre for one storefront, in Sunday–Saturday weeks that
 * Apple publishes on Mondays at 07:00 UTC and keeps for 65 weeks.
 *
 * Discovery reads, on each publication Monday, the top terms of every genre for
 * the week that just ended, with the rank each one held the week before. The
 * term is the Item's title, Apple's popularity (1–100) its measured quantity.
 * Measurement asks for one query by name: a week's Volume is the popularity
 * Apple reports for it, Apple's week answering for the ISO week that starts the
 * next day. A term Apple does not rank has no Volume, never a zero.
 */
final class AppleAds implements NeedsCredentials, PublishesWeekly, SourceCollector, SourceMeasurement
{
    private const API = 'https://api.ads.apple.com';

    private const PATH = '/v1/insights/apps/search-term-popularity/query';

    private const PAGE_SIZE = 5000;

    private const MAX_PAGES = 20;

    private const RETAINED_WEEKS = 65;

    private const MAX_ATTEMPTS = 5;

    private const MAX_BACKOFF = 16;

    public function __construct(private readonly Factory $http) {}

    public function key(): string
    {
        return 'apple_ads';
    }

    public function configured(): bool
    {
        $config = config('trend.apple_ads');

        return collect(['client_id', 'team_id', 'key_id', 'ad_account_id', 'private_key_path'])->every(fn (string $key) => filled($config[$key] ?? null))
            && is_readable($config['private_key_path']);
    }

    /** Apple publishes a week on the Monday after it ends, at 07:00 UTC; nothing comes out on other days. */
    public function publishes(CarbonImmutable $day): bool
    {
        $day = $day->utc()->startOfDay();

        return $day->isMonday() && CarbonImmutable::now('UTC')->gte($day->setTime(7, 0));
    }

    public function collectForDay(Source $source, CarbonImmutable $day): CollectedDay
    {
        $day = $day->utc()->startOfDay();

        if (! $day->isMonday()) {
            return new CollectedDay($day, []);
        }

        $week = $day->subDays(8); // the Sunday that started the week published today
        $depth = config('trend.apple_ads.top_terms');
        $current = $this->ranked($week, $depth);

        if ($current === []) {
            throw new UnexpectedValueException("Apple has not published the week of {$week->toDateString()} yet.");
        }

        $previous = $this->ranked($week->subWeek(), 2 * $depth);

        // Without last week's list every term would look new; that must fail, not propose them all.
        if ($previous === []) {
            throw new UnexpectedValueException("Apple has no list for the week of {$week->subWeek()->toDateString()} to compare with.");
        }

        $before = [];

        foreach ($previous as $row) {
            $before[$row['genre'].':'.$row['searchTerm']] = (int) $row['rankInGenre'];
        }

        $items = array_map(function (array $row) use ($before, $week, $depth) {
            $id = $row['genre'].':'.$row['searchTerm'];
            $lastWeek = isset($before[$id]) ? 'last week '.$before[$id] : 'last week below '.(2 * $depth);

            return new CollectedItem(
                externalId: $id,
                title: (string) $row['searchTerm'],
                excerpt: "{$row['genre']}, rank {$row['rankInGenre']}, {$lastWeek}",
                publishedAt: $week,
                measuredQuantity: (int) ($row['searchPopularity1to100'] ?? 0),
            );
        }, $current);

        return new CollectedDay($day, $items);
    }

    public function volumes(string $query, array $weeks): array
    {
        if ($weeks === []) {
            return [];
        }

        sort($weeks);
        $volumes = array_fill_keys($weeks, null);
        $oldest = $this->latestPublishedWeek()->subWeeks(self::RETAINED_WEEKS - 1);
        $asked = array_values(array_filter($weeks, fn (string $monday) => CarbonImmutable::parse($monday, 'UTC')->subDay()->gte($oldest)));

        if ($asked === []) {
            return $volumes;
        }

        $first = CarbonImmutable::parse($asked[0], 'UTC')->subDay();
        $last = CarbonImmutable::parse(end($asked), 'UTC')->subDay();

        // An unpublished week must fail, not be stored as a week Apple had nothing for.
        if ($last->gt($this->latestPublishedWeek()) || ! $this->published($last)) {
            throw new RuntimeException("Apple has not published the week of {$last->toDateString()} yet; it is due {$last->addDays(8)->format('Y-m-d')} 07:00 UTC.");
        }

        $rows = $this->rows([
            'fields' => ['searchPopularity1to100'],
            'filters' => [
                ['field' => 'countryOrRegion', 'operator' => 'EQUALS', 'value' => $this->storefront()],
                ['field' => 'searchTerm', 'operator' => 'EQUALS', 'value' => trim($query), 'ignoreCase' => true],
            ],
            'timeRange' => $this->weeks($first, $last),
        ]);

        foreach ($rows as $row) {
            $monday = CarbonImmutable::parse((string) $row['week'], 'UTC')->addDay()->toDateString();

            if (array_key_exists($monday, $volumes) && isset($row['searchPopularity1to100'])) {
                $volumes[$monday] = max($volumes[$monday] ?? 0, (int) $row['searchPopularity1to100']);
            }
        }

        return $volumes;
    }

    /** The Sunday that started the newest week Apple should have published by now. */
    private function latestPublishedWeek(): CarbonImmutable
    {
        $now = CarbonImmutable::now('UTC');
        $monday = $now->startOfWeek()->setTime(7, 0);

        return ($now->lt($monday) ? $monday->subWeek() : $monday)->startOfDay()->subDays(8);
    }

    /** Whether Apple has actually published the week: any genre's top term answers. Only a yes is remembered. */
    private function published(CarbonImmutable $sunday): bool
    {
        $key = 'apple_ads.published.'.$this->storefront().'.'.$sunday->toDateString();

        if (Cache::get($key) === true) {
            return true;
        }

        $rows = $this->send([
            'fields' => ['rankInGenre'],
            'filters' => [
                ['field' => 'countryOrRegion', 'operator' => 'EQUALS', 'value' => $this->storefront()],
                ['field' => 'rankInGenre', 'operator' => 'EQUALS', 'value' => 1],
            ],
            'timeRange' => $this->weeks($sunday, $sunday),
            'pagination' => ['offset' => 0, 'pageSize' => 1],
        ])->json('result.rows');

        if (is_array($rows) && $rows !== []) {
            Cache::forever($key, true);

            return true;
        }

        return false;
    }

    /**
     * Every genre's top terms for the week starting on the given Sunday, best first.
     *
     * @return list<array<string, mixed>>
     */
    private function ranked(CarbonImmutable $sunday, int $depth): array
    {
        return $this->rows([
            'fields' => ['rankInGenre', 'searchPopularity1to100'],
            'filters' => [
                ['field' => 'countryOrRegion', 'operator' => 'EQUALS', 'value' => $this->storefront()],
                ['field' => 'rankInGenre', 'operator' => 'LESS_THAN_OR_EQUAL_TO', 'value' => $depth],
            ],
            'sorting' => [['field' => 'genre', 'order' => 'ASC'], ['field' => 'rankInGenre', 'order' => 'ASC']],
            'timeRange' => $this->weeks($sunday, $sunday),
        ]);
    }

    /**
     * Every row the query matches, page by page.
     *
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function rows(array $body): array
    {
        $rows = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $offset = $page * self::PAGE_SIZE;
            $answered = $this->send([...$body, 'pagination' => ['offset' => $offset, 'pageSize' => self::PAGE_SIZE]])->json('result.rows');

            if (! is_array($answered)) {
                throw new UnexpectedValueException('Apple Ads answered without result rows.');
            }

            array_push($rows, ...$answered);

            if (count($answered) < self::PAGE_SIZE) {
                return $rows;
            }
        }

        throw new RuntimeException('Apple Ads still had more rows after '.self::MAX_PAGES.' pages.');
    }

    /**
     * One request, paced by Apple's rate-limit headers: wait out the window when it
     * is nearly spent, and on a 429 wait what Retry-After says, doubling up to a cap.
     *
     * @param  array<string, mixed>  $body
     */
    private function send(array $body): Response
    {
        $backoff = 2;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = SourceHttp::client($this->http, self::API)
                ->retry([500, 2000, 5000], when: fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()), throw: false)
                ->withToken($this->accessToken())
                ->withHeaders(['X-AP-Context' => 'adAccountId='.config('trend.apple_ads.ad_account_id')])
                ->post(self::PATH, $body);

            if ($response->status() === 429) {
                Sleep::sleep($this->retryAfter($response) ?? $backoff);
                $backoff = min(2 * $backoff, self::MAX_BACKOFF);

                continue;
            }

            $response->throw();

            if ($response->header('RateLimit-Remaining') !== '' && (int) $response->header('RateLimit-Remaining') < 2) {
                Sleep::sleep((int) $response->header('RateLimit-Reset'));
            }

            return $response;
        }

        throw new RuntimeException('Apple Ads kept answering 429 after '.self::MAX_ATTEMPTS.' attempts.');
    }

    /** Seconds to wait after a 429: Retry-After as seconds or as an HTTP date, else RateLimit-Reset. */
    private function retryAfter(Response $response): ?int
    {
        $retryAfter = trim($response->header('Retry-After'));

        if (is_numeric($retryAfter)) {
            return max(0, (int) $retryAfter);
        }

        if ($retryAfter !== '' && ($at = strtotime($retryAfter)) !== false) {
            return max(0, $at - CarbonImmutable::now('UTC')->getTimestamp());
        }

        return is_numeric($response->header('RateLimit-Reset')) ? max(0, (int) $response->header('RateLimit-Reset')) : null;
    }

    /** @return array{start: string, end: string, granularity: string} */
    private function weeks(CarbonImmutable $firstSunday, CarbonImmutable $lastSunday): array
    {
        return ['start' => $firstSunday->toDateString(), 'end' => $lastSunday->addDays(6)->toDateString(), 'granularity' => 'WEEKLY_SUN_SAT'];
    }

    private function storefront(): string
    {
        return config('trend.apple_ads.storefront');
    }

    private function accessToken(): string
    {
        return Cache::remember('apple_ads.access_token', 50 * 60, function () {
            $token = $this->http->asForm()->post('https://appleid.apple.com/auth/oauth2/token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('trend.apple_ads.client_id'),
                'client_secret' => $this->clientSecret(),
                'scope' => 'searchadsorg',
            ])->throw()->json('access_token');

            return is_string($token) ? $token : throw new UnexpectedValueException('Apple refused the client secret.');
        });
    }

    /** The client secret: an ES256 JWT signed with the private key whose public half is uploaded to Apple Ads. */
    private function clientSecret(): string
    {
        $config = config('trend.apple_ads');
        $now = CarbonImmutable::now('UTC')->getTimestamp();
        $encode = fn (array $part) => $this->base64Url(json_encode($part, JSON_THROW_ON_ERROR));
        $input = $encode(['alg' => 'ES256', 'kid' => $config['key_id']]).'.'.$encode([
            'iss' => $config['team_id'],
            'sub' => $config['client_id'],
            'aud' => 'https://appleid.apple.com',
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        $key = openssl_pkey_get_private((string) file_get_contents($config['private_key_path']));

        if ($key === false || ! openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The Apple Ads private key could not sign the client secret.');
        }

        return $input.'.'.$this->base64Url(self::rawSignature($der));
    }

    /** OpenSSL signs in DER; a JWT carries ES256 as the two 32-byte integers R and S, back to back. */
    public static function rawSignature(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0);
        $raw = '';

        foreach ([0, 1] as $ignored) {
            $length = ord($der[$offset + 1]);
            $integer = ltrim(substr($der, $offset + 2, $length), "\x00");
            $raw .= str_pad($integer, 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $raw;
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
