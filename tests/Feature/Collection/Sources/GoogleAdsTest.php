<?php

namespace Tests\Feature\Collection\Sources;

use App\Collection\Sources\GoogleAds;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures;
use Tests\TestCase;

class GoogleAdsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['trend.google_ads' => [
            'client_id' => 'client', 'client_secret' => 'secret', 'refresh_token' => 'refresh',
            'customer_id' => '7588048331', 'login_customer_id' => null, 'currency' => 'TRY', 'refresh_after_days' => 28,
        ]]);
    }

    public function test_it_reads_worldwide_keyword_metrics_with_the_account_currency(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'expires_in' => 3599]),
            'googleads.googleapis.com/*' => Http::response(Fixtures::json('GoogleAds/svelte.json')),
        ]);

        $metrics = $this->app->make(GoogleAds::class)->metrics('svelte');

        $this->assertSame(90500, $metrics['avg_monthly_searches']);
        $this->assertCount(12, $metrics['monthly']);
        $this->assertSame(['2026-08' => 110000, '2026-09' => 110000], array_slice($metrics['monthly'], -2));
        $this->assertSame(27966173, $metrics['low_bid_micros'], 'micros of the account currency, never converted');
        $this->assertSame('TRY', $metrics['currency']);
        $this->assertSame(4, $metrics['competition_index']);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v25/customers/7588048331:generateKeywordHistoricalMetrics')
            && ! $request->hasHeader('developer-token')
            && $request->hasHeader('Authorization', 'Bearer access')
            && $request['keywords'] === ['svelte']
            && ! isset($request['geoTargetConstants']));
    }

    public function test_a_keyword_without_metrics_has_none(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access']),
            'googleads.googleapis.com/*' => Http::response(['results' => [['text' => 'zzqx']]]),
        ]);

        $this->assertNull($this->app->make(GoogleAds::class)->metrics('zzqx'));
    }

    public function test_it_is_only_configured_with_every_credential(): void
    {
        $this->assertTrue($this->app->make(GoogleAds::class)->configured());

        config(['trend.google_ads.refresh_token' => null]);

        $this->assertFalse($this->app->make(GoogleAds::class)->configured());
    }
}
