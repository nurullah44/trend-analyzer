<?php

namespace App\Collection\Sources;

use App\Collection\SourceHttp;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use UnexpectedValueException;

/**
 * Google Ads keyword metrics (GenerateKeywordHistoricalMetrics): validation and
 * a Mainstream marker (ADR-0005). Read worldwide in English — omitting the geo
 * returns worldwide figures. Bid figures are micros of the account's currency,
 * never assumed to be USD, so the currency is stored with them.
 */
final class GoogleAds
{
    private const API = 'https://googleads.googleapis.com/v25';

    private const MONTHS = ['JANUARY' => 1, 'FEBRUARY' => 2, 'MARCH' => 3, 'APRIL' => 4, 'MAY' => 5, 'JUNE' => 6, 'JULY' => 7, 'AUGUST' => 8, 'SEPTEMBER' => 9, 'OCTOBER' => 10, 'NOVEMBER' => 11, 'DECEMBER' => 12];

    public function __construct(private readonly Factory $http) {}

    public function configured(): bool
    {
        return collect(['client_id', 'client_secret', 'refresh_token', 'developer_token', 'customer_id'])
            ->every(fn (string $key) => filled(config("trend.google_ads.{$key}")));
    }

    /**
     * The keyword's metrics, or null when Google has none for it.
     *
     * @return array{avg_monthly_searches: int, monthly: array<string, int>, competition: ?string, competition_index: ?int, low_bid_micros: ?int, high_bid_micros: ?int, currency: string}|null
     */
    public function metrics(string $keyword): ?array
    {
        $config = config('trend.google_ads');

        $request = SourceHttp::client($this->http, self::API)
            ->withToken($this->accessToken())
            ->withHeaders(array_filter(['developer-token' => $config['developer_token'], 'login-customer-id' => $config['login_customer_id']]));

        $result = $request->post("/customers/{$config['customer_id']}:generateKeywordHistoricalMetrics", [
            'keywords' => [$keyword],
            'language' => 'languageConstants/1000',
            'keywordPlanNetwork' => 'GOOGLE_SEARCH',
        ])->throw()->json('results.0');

        $metrics = $result['keywordMetrics'] ?? null;

        if (! is_array($metrics)) {
            return null;
        }

        $monthly = [];

        foreach ($metrics['monthlySearchVolumes'] ?? [] as $point) {
            $month = self::MONTHS[$point['month'] ?? ''] ?? throw new UnexpectedValueException('Google Ads sent an unknown month.');
            $monthly[sprintf('%04d-%02d', (int) $point['year'], $month)] = (int) ($point['monthlySearches'] ?? 0);
        }

        ksort($monthly);

        return [
            'avg_monthly_searches' => (int) ($metrics['avgMonthlySearches'] ?? 0),
            'monthly' => $monthly,
            'competition' => $metrics['competition'] ?? null,
            'competition_index' => isset($metrics['competitionIndex']) ? (int) $metrics['competitionIndex'] : null,
            'low_bid_micros' => isset($metrics['lowTopOfPageBidMicros']) ? (int) $metrics['lowTopOfPageBidMicros'] : null,
            'high_bid_micros' => isset($metrics['highTopOfPageBidMicros']) ? (int) $metrics['highTopOfPageBidMicros'] : null,
            'currency' => $config['currency'],
        ];
    }

    private function accessToken(): string
    {
        return Cache::remember('google_ads.access_token', 45 * 60, function () {
            $token = $this->http->asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('trend.google_ads.client_id'),
                'client_secret' => config('trend.google_ads.client_secret'),
                'refresh_token' => config('trend.google_ads.refresh_token'),
                'grant_type' => 'refresh_token',
            ])->throw()->json('access_token');

            return is_string($token) ? $token : throw new UnexpectedValueException('Google refused the refresh token.');
        });
    }
}
