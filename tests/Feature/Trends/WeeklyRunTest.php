<?php

namespace Tests\Feature\Trends;

use App\Collection\SourceRegistry;
use App\Enums\SubjectState;
use App\Models\Source;
use App\Models\SubjectWeek;
use App\Subjects\Intake;
use App\Trends\WeeklyRun;
use Carbon\CarbonImmutable;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeMeasurement;
use Tests\TestCase;

class WeeklyRunTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SourceSeeder::class);
        $this->week = CarbonImmutable::parse('2026-09-21', 'UTC');
        $this->travelTo($this->week->addWeek()->addDay());
    }

    public function test_a_new_subject_is_backfilled_then_only_the_missing_week_is_asked(): void
    {
        $hn = $this->measuring(new FakeMeasurement('hacker_news', 5));
        $subject = $this->app->make(Intake::class)->seed('Tidewave');

        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertCount(9, $hn->asked, 'eight baseline weeks and the scored week');
        $this->assertContains('Tidewave@2026-07-27', $hn->asked);
        $this->assertSame(9, $subject->weeks()->count());

        $this->app->make(WeeklyRun::class)->run($this->week->addWeek());

        $this->assertCount(10, $hn->asked, 'only the new week');
    }

    public function test_re_running_the_same_week_changes_nothing(): void
    {
        $this->measuring(new FakeMeasurement('hacker_news', $this->rise()), new FakeMeasurement('stack_exchange', $this->rise()));
        $subject = $this->app->make(Intake::class)->seed('Tidewave');

        $first = $this->app->make(WeeklyRun::class)->run($this->week);
        $second = $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertSame(['tidewave' => 'trending'], $first['moved']);
        $this->assertSame([], $second['moved']);
        $this->assertSame(SubjectState::Trending, $subject->fresh()->state);
    }

    public function test_every_move_is_recorded_with_the_numbers_behind_it(): void
    {
        $this->measuring(new FakeMeasurement('hacker_news', $this->rise()), new FakeMeasurement('stack_exchange', 5));
        $subject = $this->app->make(Intake::class)->seed('Tidewave');

        $this->app->make(WeeklyRun::class)->run($this->week);

        $event = $subject->events()->where('type', 'state_changed')->sole();
        $this->assertSame(['watching', 'rising', 'accelerating'], [$event->from_state, $event->to_state, $event->reason]);
        $this->assertSame('2026-09-21', $event->payload['week']);
        $this->assertEquals(['hacker_news' => 30, 'stack_exchange' => 5], $event->payload['volumes']);
        $this->assertSame(['hacker_news'], $event->payload['rising']);
    }

    public function test_a_failing_source_never_stops_another_and_is_asked_again_next_run(): void
    {
        $this->measuring(new FakeMeasurement('hacker_news', failing: true), new FakeMeasurement('stack_exchange', 5));
        $subject = $this->app->make(Intake::class)->seed('Tidewave');

        $result = $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertSame(['hacker_news' => 'hacker_news is down'], $result['failures']);
        $this->assertSame(9, $subject->weeks()->count(), 'Stack Exchange still measured');
        $this->assertSame('hacker_news is down', Source::where('key', 'hacker_news')->value('last_error'));

        $this->measuring(new FakeMeasurement('hacker_news', 5));
        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertNull(Source::where('key', 'hacker_news')->value('last_error'), 'a recovered Source is healthy again');
    }

    public function test_a_failed_measurement_never_moves_a_subject(): void
    {
        $this->measuring(new FakeMeasurement('hacker_news', failing: true), new FakeMeasurement('stack_exchange', 5));
        $subject = $this->app->make(Intake::class)->seed('Tidewave');
        $subject->moveTo(SubjectState::Rising, 'test');

        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertSame(SubjectState::Rising, $subject->fresh()->state);
    }

    public function test_an_older_week_never_moves_a_subject_scored_on_a_newer_one(): void
    {
        $this->travelTo($this->week->addWeeks(2)->addDay());
        $rise = ['2026-09-28' => 30] + array_fill_keys(array_map(fn (int $back) => $this->week->addWeek()->subWeeks($back)->toDateString(), range(1, 8)), 5);
        $this->measuring(new FakeMeasurement('hacker_news', $rise), new FakeMeasurement('stack_exchange', $rise));
        $subject = $this->app->make(Intake::class)->seed('Tidewave');

        $this->app->make(WeeklyRun::class)->run($this->week->addWeek());
        $rerun = $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertSame(SubjectState::Trending, $subject->fresh()->state);
        $this->assertSame([], $rerun['moved']);
    }

    public function test_a_new_query_starts_a_new_series(): void
    {
        $this->measuring(new FakeMeasurement('hacker_news', 5));
        $subject = $this->app->make(Intake::class)->seed('MCP');
        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->app->make(Intake::class)->seed('MCP', 'Model Context Protocol');
        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertSame(18, $subject->weeks()->count(), 'the old series is kept, the new query measured from scratch');
        $this->assertSame(9, $subject->weeks()->where('query', 'Model Context Protocol')->count());
    }

    public function test_nothing_measurable_is_stored_as_null_and_not_asked_again(): void
    {
        $wikimedia = $this->measuring(new FakeMeasurement('wikimedia', null));
        $this->app->make(Intake::class)->seed('Tidewave');

        $this->app->make(WeeklyRun::class)->run($this->week);
        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertCount(9, $wikimedia->asked);
        $this->assertSame(9, SubjectWeek::whereNull('volume')->count());
    }

    public function test_backlog_and_archived_subjects_are_not_measured(): void
    {
        $hn = $this->measuring(new FakeMeasurement('hacker_news', 5));
        config(['trend.classifier.key' => null]);
        $this->app->make(Intake::class)->seed('Tidewave')->moveTo(SubjectState::Archived, 'test');

        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertSame([], $hn->asked);
    }

    public function test_the_command_refuses_a_week_that_is_not_a_finished_monday(): void
    {
        $this->artisan('trends:weekly', ['--week' => '2026-09-22'])->assertFailed();
        $this->artisan('trends:weekly', ['--week' => '2026-09-28'])->assertFailed();
        $this->measuring();
        $this->artisan('trends:weekly', ['--week' => '2026-09-21'])->assertSuccessful();
    }

    /** @return array<string, int> a flat baseline of five, then thirty in the scored week */
    private function rise(): array
    {
        return ['2026-09-21' => 30] + array_fill_keys(array_map(fn (int $back) => $this->week->subWeeks($back)->toDateString(), range(1, 8)), 5);
    }

    private function measuring(FakeMeasurement ...$measurements): ?FakeMeasurement
    {
        $byKey = [];

        foreach ($measurements as $measurement) {
            $byKey[$measurement->key()] = $measurement;
        }

        $this->app->instance(SourceRegistry::class, new SourceRegistry([], $byKey));

        return $measurements[0] ?? null;
    }
}
