<?php

namespace Tests\Feature\Trends;

use App\Collection\CollectedItem;
use App\Collection\SourceRegistry;
use App\Enums\SubjectState;
use App\Models\Alarm;
use App\Models\Item;
use App\Models\Source;
use App\Models\Subject;
use App\Read\Ledger;
use App\Subjects\Intake;
use Carbon\CarbonImmutable;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeCollector;
use Tests\Support\FakeMeasurement;
use Tests\TestCase;

/** Discovery → Subject → series → Alarm → report, driven through the commands with fake Sources and no network. */
class PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_day_collected_but_never_discovered_is_discovered_on_the_next_run(): void
    {
        $this->seed(SourceSeeder::class);
        config(['trend.classifier.key' => null]);
        $this->travelTo(CarbonImmutable::parse('2026-09-28 06:00', 'UTC'));
        $this->app->instance(SourceRegistry::class, new SourceRegistry(['hacker_news' => FakeCollector::returning('hacker_news')]));
        Item::create(['source_id' => Source::where('key', 'hacker_news')->value('id'), 'external_id' => '9', 'title' => 'Show HN: Tidewave – agents', 'observed_on' => '2026-09-27']);

        $this->artisan('trends:daily')->assertSuccessful();

        $this->assertTrue(Subject::where('slug', 'tidewave')->exists());
    }

    public function test_a_run_already_in_progress_is_not_started_twice(): void
    {
        $lock = Cache::lock('run:trends:weekly', 60);
        $lock->get();

        $this->artisan('trends:weekly')->expectsOutputToContain('already running')->assertSuccessful();

        $lock->release();
    }

    public function test_a_corroborated_rise_becomes_an_alarm_in_the_weekly_report(): void
    {
        $this->seed(SourceSeeder::class);
        config(['trend.classifier.key' => null]);
        $this->travelTo(CarbonImmutable::parse('2026-09-28 06:00', 'UTC'));

        $week = CarbonImmutable::parse('2026-09-21', 'UTC');
        $rise = ['2026-09-21' => 30] + array_fill_keys(array_map(fn (int $back) => $week->subWeeks($back)->toDateString(), range(1, 8)), 5);
        $launch = fn (string $id) => new CollectedItem($id, 'Show HN: Tidewave – a coding agent', url: "https://example.com/{$id}", publishedAt: CarbonImmutable::parse('2026-09-27 10:00', 'UTC'), measuredQuantity: 120);
        $this->app->instance(SourceRegistry::class, new SourceRegistry(
            ['hacker_news' => FakeCollector::returning('hacker_news', $launch('1'))],
            ['hacker_news' => new FakeMeasurement('hacker_news', $rise), 'stack_exchange' => new FakeMeasurement('stack_exchange', $rise)],
        ));

        $this->artisan('trends:daily')->assertSuccessful();
        $this->assertSame(7, Item::count(), 'every missing day of the last week collected');
        $this->assertSame(SubjectState::Backlog, Subject::where('slug', 'tidewave')->sole()->state);

        $this->artisan('trends:daily')->assertSuccessful();
        $this->assertSame(7, Item::count(), 'nothing missing, nothing collected twice');

        $this->app->make(Intake::class)->seed('Tidewave');
        $this->artisan('trends:weekly')->expectsOutputToContain('tidewave → trending')->assertSuccessful();

        $alarm = Alarm::sole();
        $this->assertSame('2026-09-21', $alarm->week);
        $this->assertSame(2, $alarm->corroboration);
        $this->assertSame(['query', 'week', 'volumes', 'velocities', 'rising', 'corroboration', 'trend_score', 'series', 'items', 'links', 'keyword_metrics'], array_keys($alarm->evidence), 'Evidence only: numbers, series, Items and links');
        $this->assertSame('https://example.com/1', $alarm->evidence['items'][0]['url']);
        $this->assertSame(120, $alarm->evidence['items'][0]['measured_quantity']);

        $report = $this->app->make(Ledger::class)->report();
        $this->assertSame('2026-09-21', $report['week']);
        $this->assertSame(['Tidewave'], array_column($report['alarms'], 'subject'));
        $this->assertContains('the Classifier has no key: every Candidate waits in Backlog', $report['gaps']);

        $this->artisan('trends:report')->expectsOutputToContain('**Tidewave**')->expectsOutputToContain('## Sources')->assertSuccessful();

        $this->artisan('trends:weekly')->assertSuccessful();
        $this->assertSame(1, Alarm::count(), 'a doubled weekly run publishes nothing twice');
    }
}
