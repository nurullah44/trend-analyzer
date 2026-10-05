<?php

namespace Tests\Feature\Collection\Sources;

use App\Collection\SourceRegistry;
use App\Collection\Sources\AppleAds;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\Support\Fixtures;
use Tests\TestCase;
use UnexpectedValueException;

class AppleAdsTest extends TestCase
{
    use RefreshDatabase;

    private const QUERY = 'api.ads.apple.com/v1/insights/apps/search-term-popularity/query';

    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'apple-ads-key');
        file_put_contents($this->keyPath, $pem);

        config(['trend.apple_ads' => [
            'client_id' => 'SEARCHADS.client', 'team_id' => 'SEARCHADS.team', 'key_id' => 'key-1', 'ad_account_id' => '4242',
            'private_key_path' => $this->keyPath, 'storefront' => 'US', 'top_terms' => 500, 'min_climb' => 100, 'max_candidates' => 20,
        ]]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyPath);

        parent::tearDown();
    }

    public function test_it_trades_an_es256_client_secret_for_a_token_and_scopes_every_call_to_the_ad_account(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 09:00', 'UTC'));
        Http::fake([
            'appleid.apple.com/auth/oauth2/token' => Http::response(['access_token' => 'access', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            self::QUERY => Http::response(Fixtures::json('AppleAds/volume-pdf-scanner.json')),
        ]);

        $this->apple()->volumes('pdf scanner', ['2026-09-28']);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://appleid.apple.com/auth/oauth2/token') {
                return false;
            }

            [$header, $payload, $signature] = explode('.', $request['client_secret']);
            $claims = json_decode($this->base64UrlDecode($payload), true);

            return $request['grant_type'] === 'client_credentials'
                && $request['scope'] === 'searchadsorg'
                && $request['client_id'] === 'SEARCHADS.client'
                && json_decode($this->base64UrlDecode($header), true) === ['alg' => 'ES256', 'kid' => 'key-1']
                && $claims['iss'] === 'SEARCHADS.team' && $claims['sub'] === 'SEARCHADS.client' && $claims['aud'] === 'https://appleid.apple.com'
                && $claims['exp'] > $claims['iat']
                && openssl_verify("{$header}.{$payload}", $this->der($this->base64UrlDecode($signature)), openssl_pkey_get_details(openssl_pkey_get_private(file_get_contents($this->keyPath)))['key'], OPENSSL_ALGO_SHA256) === 1;
        });
        Http::assertSent(fn (Request $request) => str_contains($request->url(), self::QUERY)
            && $request->hasHeader('Authorization', 'Bearer access')
            && $request->hasHeader('X-AP-Context', 'adAccountId=4242'));
    }

    public function test_a_publication_monday_yields_every_genres_top_terms_with_last_weeks_rank(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 09:00', 'UTC'));
        $this->fakeApple(fn (Request $request) => Http::response(Fixtures::json('AppleAds/top-'.$request['timeRange']['start'].'.json')));

        $day = $this->apple()->collectForDay(new Source(['key' => 'apple_ads']), CarbonImmutable::parse('2026-10-05', 'UTC'));

        $this->assertSame('2026-10-05', $day->day->toDateString(), 'the Items are observed on the day Apple published them');
        $this->assertSame(['HEALTH_FITNESS:step counter', 'PRODUCTIVITY_UTILITIES:chatgpt', 'PRODUCTIVITY_UTILITIES:pdf scanner', 'PRODUCTIVITY_UTILITIES:ai note taker'], array_map(fn ($item) => $item->externalId, $day->items));
        $this->assertSame('step counter', $day->items[0]->title);
        $this->assertSame('HEALTH_FITNESS, rank 3, last week 250', $day->items[0]->excerpt);
        $this->assertSame('PRODUCTIVITY_UTILITIES, rank 40, last week below 500', $day->items[3]->excerpt, 'absent from the list the week before');
        $this->assertSame(60, $day->items[0]->measuredQuantity, "Apple's popularity, never computed");
        $this->assertSame('2026-09-27', $day->items[0]->publishedAt->toDateString(), 'the Sunday that started the week');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), self::QUERY)
            && $request['timeRange'] === ['start' => '2026-09-27', 'end' => '2026-10-03', 'granularity' => 'WEEKLY_SUN_SAT']
            && in_array(['field' => 'rankInGenre', 'operator' => 'LESS_THAN_OR_EQUAL_TO', 'value' => 500], $request['filters'], true)
            && in_array(['field' => 'countryOrRegion', 'operator' => 'EQUALS', 'value' => 'US'], $request['filters'], true));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), self::QUERY)
            && $request['timeRange']['start'] === '2026-09-20'
            && in_array(['field' => 'rankInGenre', 'operator' => 'LESS_THAN_OR_EQUAL_TO', 'value' => 500], $request['filters'], true));
    }

    public function test_apple_publishes_on_monday_at_seven_and_on_no_other_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 06:59', 'UTC'));
        $apple = $this->apple();

        $this->assertFalse($apple->publishes(CarbonImmutable::parse('2026-10-05', 'UTC')), 'not before 07:00 UTC');
        $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
        $this->assertTrue($apple->publishes(CarbonImmutable::parse('2026-10-05', 'UTC')));
        $this->assertFalse($apple->publishes(CarbonImmutable::parse('2026-10-04', 'UTC')));
        $this->assertSame([], $apple->collectForDay(new Source(['key' => 'apple_ads']), CarbonImmutable::parse('2026-10-04', 'UTC'))->items);
        Http::assertNothingSent();
    }

    public function test_an_empty_week_is_not_published_yet_rather_than_a_week_without_searches(): void
    {
        $this->fakeApple(fn () => Http::response(['result' => ['rows' => []]]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('not published the week of 2026-09-27');

        $this->apple()->collectForDay(new Source(['key' => 'apple_ads']), CarbonImmutable::parse('2026-10-05', 'UTC'));
    }

    public function test_without_last_weeks_list_no_term_is_called_new(): void
    {
        $this->fakeApple(fn (Request $request) => Http::response($request['timeRange']['start'] === '2026-09-27'
            ? Fixtures::json('AppleAds/top-2026-09-27.json')
            : ['result' => ['rows' => []]]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('no list for the week of 2026-09-20');

        $this->apple()->collectForDay(new Source(['key' => 'apple_ads']), CarbonImmutable::parse('2026-10-05', 'UTC'));
    }

    public function test_a_weeks_volume_is_apples_popularity_for_the_iso_week_that_starts_the_next_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'UTC'));
        $this->fakeApple(fn () => Http::response(Fixtures::json('AppleAds/volume-pdf-scanner.json')));

        $volumes = $this->apple()->volumes('PDF Scanner', ['2026-09-28', '2026-09-14', '2026-09-21']);

        $this->assertSame(['2026-09-14' => 62, '2026-09-21' => 59, '2026-09-28' => 59], $volumes, 'recorded live on 2026-10-05');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), self::QUERY)
            && $request['timeRange'] === ['start' => '2026-09-13', 'end' => '2026-10-03', 'granularity' => 'WEEKLY_SUN_SAT']
            && in_array(['field' => 'searchTerm', 'operator' => 'EQUALS', 'value' => 'PDF Scanner'], $request['filters'], true));
    }

    public function test_a_term_ranked_in_two_genres_takes_its_higher_popularity_and_an_unranked_week_has_no_volume(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'UTC'));
        $this->fakeApple(fn () => Http::response(['result' => ['rows' => [
            ['week' => '2026-09-20', 'genre' => 'BUSINESS', 'searchTerm' => 'pdf scanner', 'searchPopularity1to100' => 61],
            ['week' => '2026-09-20', 'genre' => 'PRODUCTIVITY_UTILITIES', 'searchTerm' => 'pdf scanner', 'searchPopularity1to100' => 59],
        ]]]));

        $this->assertSame(['2026-09-21' => 61, '2026-09-28' => null], $this->apple()->volumes('pdf scanner', ['2026-09-21', '2026-09-28']));
    }

    public function test_a_week_apple_has_not_published_fails_the_measurement(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 06:59', 'UTC'));
        $this->fakeApple(fn () => Http::response(Fixtures::json('AppleAds/volume-pdf-scanner.json')));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('due 2026-10-05 07:00 UTC');

        $this->apple()->volumes('pdf scanner', ['2026-09-28']);
    }

    public function test_a_week_apple_is_late_with_fails_rather_than_storing_no_volume(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00', 'UTC'));
        $this->fakeApple(fn () => Http::response(['result' => ['rows' => []]]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not published the week of 2026-09-27');

        $this->apple()->volumes('pdf scanner', ['2026-09-28']);
    }

    public function test_the_oldest_week_apple_still_keeps_is_asked(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'UTC'));
        $this->fakeApple(fn () => Http::response(Fixtures::json('AppleAds/volume-pdf-scanner.json')));

        $this->assertSame(['2025-06-30' => null, '2025-07-07' => null], $this->apple()->volumes('pdf scanner', ['2025-06-30', '2025-07-07']));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), self::QUERY) && ($request['timeRange']['start'] ?? null) === '2025-07-06');
    }

    public function test_weeks_older_than_apple_keeps_are_not_asked(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'UTC'));

        $this->assertSame(['2024-11-25' => null], $this->apple()->volumes('deepseek', ['2024-11-25']));
        Http::assertNothingSent();
    }

    public function test_a_429_waits_as_long_as_retry_after_says_then_retries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'UTC'));
        Http::fake([
            'appleid.apple.com/auth/oauth2/token' => Http::response(['access_token' => 'access']),
            self::QUERY => Http::sequence()
                ->push(['error' => 'rate_limit_exceeded'], 429, ['Retry-After' => '7'])
                ->push(['error' => 'rate_limit_exceeded'], 429, ['Retry-After' => 'Mon, 05 Oct 2026 08:00:20 GMT'])
                ->push(Fixtures::json('AppleAds/volume-pdf-scanner.json'), 200, ['RateLimit-Remaining' => '40', 'RateLimit-Reset' => '30'])
                ->push(Fixtures::json('AppleAds/volume-pdf-scanner.json'), 200, ['RateLimit-Remaining' => '39', 'RateLimit-Reset' => '30']),
        ]);

        $this->assertSame(59, $this->apple()->volumes('pdf scanner', ['2026-09-28'])['2026-09-28']);
        Sleep::assertSequence([Sleep::for(7)->seconds(), Sleep::for(20)->seconds()]);
    }

    public function test_it_paces_itself_when_the_window_is_nearly_spent(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'UTC'));
        $this->fakeApple(fn () => Http::response(Fixtures::json('AppleAds/volume-pdf-scanner.json'), 200, ['RateLimit-Remaining' => '1', 'RateLimit-Reset' => '37']));

        $this->apple()->volumes('pdf scanner', ['2026-09-28']);

        Sleep::assertSlept(fn (CarbonInterval $duration) => (int) $duration->totalSeconds === 37, times: 2);
    }

    public function test_without_every_credential_it_is_left_out_of_every_run(): void
    {
        $this->seed(SourceSeeder::class);
        $this->assertContains('apple_ads', array_keys($this->app->make(SourceRegistry::class)->measurements()));

        config(['trend.apple_ads.private_key_path' => '/nowhere/key.pem']);

        $registry = $this->app->make(SourceRegistry::class);
        $this->assertFalse($this->apple()->configured());
        $this->assertNotContains('apple_ads', array_keys($registry->measurements()));
        $this->assertFalse($registry->has('apple_ads'));
        $this->assertSame(['apple_ads'], $registry->unconfigured());

        $this->artisan('trends:status')->expectsOutputToContain('no credentials')->assertSuccessful();
    }

    private function apple(): AppleAds
    {
        return $this->app->make(AppleAds::class);
    }

    private function fakeApple(callable $answer): void
    {
        Http::fake([
            'appleid.apple.com/auth/oauth2/token' => Http::response(['access_token' => 'access']),
            self::QUERY => $answer,
        ]);
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }

    /** The raw R‖S signature back in DER, which is what OpenSSL verifies. */
    private function der(string $raw): string
    {
        $integer = function (string $bytes) {
            $bytes = ltrim($bytes, "\x00");

            return "\x02".chr(strlen($bytes) + (ord($bytes[0]) > 0x7F ? 1 : 0)).(ord($bytes[0]) > 0x7F ? "\x00" : '').$bytes;
        };
        $body = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32));

        return "\x30".chr(strlen($body)).$body;
    }
}
