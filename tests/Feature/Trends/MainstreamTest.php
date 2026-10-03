<?php

namespace Tests\Feature\Trends;

use App\Collection\SourceRegistry;
use App\Enums\SubjectState;
use App\Models\Alarm;
use App\Models\Source;
use App\Subjects\Intake;
use App\Trends\WeeklyRun;
use Carbon\CarbonImmutable;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeMeasurement;
use Tests\Support\Fixtures;
use Tests\TestCase;

class MainstreamTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SourceSeeder::class);
        $this->week = CarbonImmutable::parse('2026-09-21', 'UTC');
        $this->travelTo($this->week->addWeek()->addDay());
        config(['trend.google_ads.refresh_token' => null]);
    }

    public function test_a_subject_already_over_the_line_goes_straight_to_mainstream_and_never_alarms(): void
    {
        $this->views(['2026-09-14' => 80_000, '2026-09-21' => 90_000]);
        $subject = $this->app->make(Intake::class)->seed('ChatGPT');

        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertSame(SubjectState::Mainstream, $subject->fresh()->state);
        $this->assertSame(0, Alarm::count());
        $this->assertNull($subject->fresh()->lead_time_days, 'no Alarm, no lead time');
    }

    public function test_a_single_week_over_the_line_is_not_mainstream(): void
    {
        $this->views(['2026-09-14' => 1_000, '2026-09-21' => 90_000]);
        $subject = $this->app->make(Intake::class)->seed('ChatGPT');

        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertNotSame(SubjectState::Mainstream, $subject->fresh()->state);
    }

    public function test_arrival_closes_the_alarm_and_fixes_the_lead_time(): void
    {
        $this->views(['2026-09-14' => 80_000, '2026-09-21' => 90_000]);
        $subject = $this->app->make(Intake::class)->seed('Tidewave');
        $subject->moveTo(SubjectState::Trending, 'test');
        $alarm = Alarm::create(['subject_id' => $subject->id, 'week' => '2026-08-10', 'published_on' => '2026-08-17', 'state_at_publication' => 'trending', 'evidence' => []]);

        $this->app->make(WeeklyRun::class)->run($this->week);

        $subject->refresh();
        $this->assertSame(SubjectState::Mainstream, $subject->state);
        $this->assertSame('2026-09-29', $subject->mainstream_on->toDateString());
        $this->assertSame(43, $subject->lead_time_days, 'from 17 August to 29 September');
        $this->assertSame('2026-09-29', $alarm->fresh()->closed_on->toDateString());
    }

    public function test_google_searches_holding_over_the_line_are_arrival_and_rising_subjects_are_sized(): void
    {
        config(['trend.google_ads' => ['client_id' => 'c', 'client_secret' => 's', 'refresh_token' => 'r', 'customer_id' => '1', 'login_customer_id' => null, 'currency' => 'TRY', 'refresh_after_days' => 28]]);
        config(['trend.mainstream.monthly_searches' => 90_000]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a']),
            'googleads.googleapis.com/*' => Http::response(Fixtures::json('GoogleAds/svelte.json')),
        ]);
        $this->views([]);
        $subject = $this->app->make(Intake::class)->seed('svelte');
        $subject->moveTo(SubjectState::Rising, 'test');

        $this->app->make(WeeklyRun::class)->run($this->week);

        $subject->refresh();
        $this->assertSame('TRY', $subject->keyword_metrics['currency']);
        $this->assertSame('svelte', $subject->keyword_metrics['query']);
        $this->assertSame(SubjectState::Mainstream, $subject->state, 'July (90,500) and August (110,000), the two full months before the week');
    }

    public function test_months_that_are_not_the_two_before_the_scored_week_do_not_count(): void
    {
        $this->views([]);
        $subject = $this->app->make(Intake::class)->seed('svelte');
        $subject->update(['keyword_metrics' => ['query' => 'svelte', 'monthly' => ['2026-06' => 200_000, '2026-08' => 200_000, '2026-10' => 200_000]]]);

        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertNotSame(SubjectState::Mainstream, $subject->fresh()->state, 'July is missing');
    }

    public function test_figures_for_an_old_query_never_count(): void
    {
        $this->views([]);
        $subject = $this->app->make(Intake::class)->seed('svelte');
        $subject->update(['keyword_metrics' => ['query' => 'sveltekit', 'monthly' => ['2026-07' => 200_000, '2026-08' => 200_000]]]);

        $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertNotSame(SubjectState::Mainstream, $subject->fresh()->state);
    }

    public function test_a_disabled_google_ads_source_is_never_asked(): void
    {
        config(['trend.google_ads' => ['client_id' => 'c', 'client_secret' => 's', 'refresh_token' => 'r', 'customer_id' => '1', 'login_customer_id' => null, 'currency' => 'TRY', 'refresh_after_days' => 28]]);
        Source::where('key', 'google_ads')->update(['enabled' => false]);
        Http::fake();
        $this->views([]);
        $this->app->make(Intake::class)->seed('svelte')->moveTo(SubjectState::Rising, 'test');

        $this->app->make(WeeklyRun::class)->run($this->week);

        Http::assertNothingSent();
    }

    /** @param array<string, int> $views */
    private function views(array $views): void
    {
        $this->app->instance(SourceRegistry::class, new SourceRegistry([], ['wikimedia' => new FakeMeasurement('wikimedia', $views)]));
    }
}
