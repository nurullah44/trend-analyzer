<?php

namespace Tests\Feature\Trends;

use App\Collection\SourceRegistry;
use App\Models\Source;
use App\Models\Subject;
use App\Models\SubjectWeek;
use App\Trends\Backtest;
use Carbon\CarbonImmutable;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeMeasurement;
use Tests\Support\Fixtures;
use Tests\TestCase;

class BacktestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SourceSeeder::class);
        config(['trend.google_ads.refresh_token' => null]);
    }

    public function test_a_known_rise_is_flagged_before_its_marker_and_a_dud_never_is(): void
    {
        $from = CarbonImmutable::parse('2026-06-01', 'UTC');
        $to = CarbonImmutable::parse('2026-09-21', 'UTC');
        $weeks = fn (callable $volume) => collect(range(-8, 16))->mapWithKeys(fn (int $i) => [$from->addWeeks($i)->toDateString() => $volume($i)])->all();

        // A rise that starts in week 6 on two Sources, and reaches 80,000 Wikipedia views a week from week 12.
        $rise = $weeks(fn (int $i) => $i < 6 ? 5 : 5 + ($i - 5) * 10);
        $views = $weeks(fn (int $i) => $i < 12 ? 1_000 : 80_000);
        $flat = $weeks(fn (int $i) => 5 + $i % 2);

        $this->app->instance(SourceRegistry::class, new SourceRegistry([], [
            'hacker_news' => new FakeMeasurement('hacker_news', $rise),
            'stack_exchange' => new FakeMeasurement('stack_exchange', $rise),
            'wikimedia' => new FakeMeasurement('wikimedia', $views),
        ]));

        $breakout = $this->app->make(Backtest::class)->replay('Tidewave', $from, $to);

        $this->assertSame('2026-07-13', $breakout['trending'], 'caught the corroborated rise');
        $this->assertSame('2026-08-31', $breakout['mainstream'], 'the second week over the line');
        $this->assertSame(7, $breakout['lead_weeks']);

        $this->app->instance(SourceRegistry::class, new SourceRegistry([], [
            'hacker_news' => new FakeMeasurement('hacker_news', $flat),
            'stack_exchange' => new FakeMeasurement('stack_exchange', $flat),
        ]));

        $dud = $this->app->make(Backtest::class)->replay('Blobby', $from, $to);

        $this->assertNull($dud['rising']);
        $this->assertNull($dud['trending']);
        $this->assertSame(0, Subject::count() + SubjectWeek::count(), 'a backtest writes nothing');
    }

    public function test_a_disabled_source_does_not_count_and_google_ads_can_mark_arrival(): void
    {
        $from = CarbonImmutable::parse('2026-06-01', 'UTC');
        $to = CarbonImmutable::parse('2026-09-21', 'UTC');
        $rise = collect(range(-8, 16))->mapWithKeys(fn (int $i) => [$from->addWeeks($i)->toDateString() => $i < 6 ? 5 : 5 + ($i - 5) * 10])->all();
        $this->app->instance(SourceRegistry::class, new SourceRegistry([], [
            'hacker_news' => new FakeMeasurement('hacker_news', $rise),
            'stack_exchange' => new FakeMeasurement('stack_exchange', $rise),
        ]));
        Source::where('key', 'stack_exchange')->update(['enabled' => false]);

        $this->assertNull($this->app->make(Backtest::class)->replay('Tidewave', $from, $to)['trending'], 'one enabled Source cannot corroborate');

        config(['trend.google_ads' => ['client_id' => 'c', 'client_secret' => 's', 'refresh_token' => 'r', 'customer_id' => '1', 'login_customer_id' => null, 'currency' => 'TRY', 'refresh_after_days' => 28]]);
        config(['trend.mainstream.monthly_searches' => 90_000]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a']),
            'googleads.googleapis.com/*' => Http::response(Fixtures::json('GoogleAds/svelte.json')),
        ]);

        $this->assertSame('2026-06-01', $this->app->make(Backtest::class)->replay('svelte', $from, $to)['mainstream'], 'April and May (90,500 each) already held over the line');
    }

    public function test_a_failing_source_is_reported_not_thrown(): void
    {
        $this->app->instance(SourceRegistry::class, new SourceRegistry([], ['hacker_news' => new FakeMeasurement('hacker_news', failing: true)]));

        $result = $this->app->make(Backtest::class)->replay('Tidewave', CarbonImmutable::parse('2026-09-07'), CarbonImmutable::parse('2026-09-21'));

        $this->assertSame('hacker_news is down', $result['failure']);
    }

    public function test_the_command_replays_the_given_queries(): void
    {
        $this->app->instance(SourceRegistry::class, new SourceRegistry([], ['hacker_news' => new FakeMeasurement('hacker_news', 5)]));

        $this->artisan('trends:backtest', ['query' => ['Tidewave'], '--weeks' => 4])->expectsOutputToContain('Tidewave')->assertSuccessful();
    }
}
