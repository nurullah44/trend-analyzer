<?php

namespace Tests\Feature;

use App\Collection\CollectedItem;
use App\Collection\SourceRegistry;
use App\Models\Item;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeCollector;
use Tests\TestCase;

class TrendsCollectCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_collects_a_source_for_a_day_and_records_health(): void
    {
        $this->seed(SourceSeeder::class);
        Http::preventStrayRequests();
        Http::fake(['api.stackexchange.com/*' => Http::response($this->fixture('2026-09-11-page-1.json'))]);

        $this->artisan('trends:collect', ['--source' => 'stack_exchange', '--day' => '2026-09-11'])
            ->expectsOutputToContain('stack_exchange')
            ->expectsOutputToContain('49')
            ->assertSuccessful();

        $this->assertSame(49, Item::query()->count());
        $this->assertSame('2026-09-11', Item::query()->orderBy('id')->firstOrFail()->observed_on->toDateString());

        $source = Source::query()->where('key', 'stack_exchange')->firstOrFail();
        $this->assertSame(49, $source->last_item_count);
        $this->assertNotNull($source->last_success_at);
        $this->assertNull($source->last_error);
    }

    public function test_it_defaults_to_yesterday_in_utc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-12 02:00:00', 'UTC'));
        $this->seed(SourceSeeder::class);

        $collector = FakeCollector::returning('stack_exchange');
        $this->app->instance(SourceRegistry::class, new SourceRegistry(['stack_exchange' => $collector]));

        $this->artisan('trends:collect', ['--source' => 'stack_exchange'])->assertSuccessful();

        $this->assertSame('2026-09-11', $collector->requestedDays[0]->toDateString());
    }

    public function test_a_failing_source_does_not_stop_the_others_and_the_run_reports_failure(): void
    {
        $this->seed(SourceSeeder::class);
        $this->app->instance(SourceRegistry::class, new SourceRegistry([
            'stack_exchange' => FakeCollector::returning('stack_exchange', $this->question('q-1')),
            'hacker_news' => FakeCollector::failing('hacker_news', 'the Source is down'),
        ]));

        $this->artisan('trends:collect', ['--day' => '2026-09-11'])
            ->expectsOutputToContain('the Source is down')
            ->assertFailed();

        $this->assertSame(1, Item::query()->count(), 'the working Source still collected');
        $this->assertSame('the Source is down', Source::query()->where('key', 'hacker_news')->firstOrFail()->last_error);
        $this->assertNull(Source::query()->where('key', 'stack_exchange')->firstOrFail()->last_error);
    }

    public function test_it_refuses_a_source_that_is_not_in_the_approved_list(): void
    {
        $this->seed(SourceSeeder::class);
        Http::fake();

        $this->artisan('trends:collect', ['--source' => 'tiktok'])
            ->expectsOutputToContain('approved list')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_refuses_a_disabled_source(): void
    {
        $this->seed(SourceSeeder::class);
        Http::fake();

        $this->artisan('trends:collect', ['--source' => 'reddit'])
            ->expectsOutputToContain('disabled')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_refuses_an_enabled_source_that_has_no_collector_yet(): void
    {
        $this->seed(SourceSeeder::class);
        Http::fake();

        $this->artisan('trends:collect', ['--source' => 'hacker_news'])
            ->expectsOutputToContain('no collector yet')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_refuses_a_day_that_is_not_a_date(): void
    {
        $this->seed(SourceSeeder::class);

        $this->artisan('trends:collect', ['--source' => 'stack_exchange', '--day' => 'yesterday'])
            ->expectsOutputToContain('YYYY-MM-DD')
            ->assertFailed();
    }

    public function test_it_names_the_enabled_sources_that_have_no_collector_yet(): void
    {
        $this->seed(SourceSeeder::class);
        $this->app->instance(SourceRegistry::class, new SourceRegistry([
            'stack_exchange' => FakeCollector::returning('stack_exchange'),
        ]));

        $this->artisan('trends:collect', ['--day' => '2026-09-11'])
            ->expectsOutputToContain('hacker_news')
            ->assertSuccessful();
    }

    public function test_status_shows_the_health_a_collection_recorded(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-12 07:30:00', 'Europe/Istanbul'));
        $this->seed(SourceSeeder::class);
        $this->app->instance(SourceRegistry::class, new SourceRegistry([
            'stack_exchange' => FakeCollector::returning('stack_exchange', $this->question('q-1'), $this->question('q-2')),
        ]));

        $this->artisan('trends:collect', ['--source' => 'stack_exchange', '--day' => '2026-09-11'])->assertSuccessful();

        $this->assertSame(0, Artisan::call('trends:status'));

        $this->assertMatchesRegularExpression(
            '/stack_exchange\s+\| yes\s+\| discovery\s+\| global\s+\| 2026-09-12 07:30\s+\| 2026-09-12 07:30\s+\| 2\s+\|/',
            Artisan::output(),
        );
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        $path = base_path("tests/Fixtures/StackExchange/{$name}");

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    private function question(string $id): CollectedItem
    {
        return new CollectedItem(externalId: $id, title: 'A question');
    }
}
