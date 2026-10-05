<?php

namespace Tests\Feature\Trends;

use App\Collection\SourceRegistry;
use App\Enums\SubjectState;
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

class CompetitionTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SourceSeeder::class);
        $this->week = CarbonImmutable::parse('2026-09-21', 'UTC');
        $this->travelTo($this->week->addWeek()->addDay());
        $this->app->instance(SourceRegistry::class, new SourceRegistry([], ['wikimedia' => new FakeMeasurement('wikimedia', null)]));
    }

    public function test_a_rising_subject_gets_the_apps_already_answering_its_search_once_a_period(): void
    {
        Http::fake(['itunes.apple.com/search*' => Http::response(Fixtures::json('AppStoreSearch/pdf-scanner.json'))]);
        $subject = $this->app->make(Intake::class)->seed('pdf scanner');
        $subject->moveTo(SubjectState::Rising, 'test');

        $this->app->make(WeeklyRun::class)->run($this->week);
        $this->app->make(WeeklyRun::class)->run($this->week->addWeek());

        $competition = $subject->fresh()->app_competition;
        $this->assertSame('pdf scanner', $competition['query']);
        $this->assertSame('Adobe Scan: PDF & Doc Scanner', $competition['apps'][0]['name']);
        Http::assertSentCount(1);
    }

    public function test_a_watching_subject_is_not_looked_up_and_a_failure_never_blocks_the_run(): void
    {
        Http::fake(['itunes.apple.com/search*' => Http::response('', 503)]);
        $quiet = $this->app->make(Intake::class)->seed('pdf scanner');
        $rising = $this->app->make(Intake::class)->seed('plant identifier');
        $rising->moveTo(SubjectState::Rising, 'test');

        $result = $this->app->make(WeeklyRun::class)->run($this->week);

        $this->assertSame(2, $result['scored']);
        $this->assertNull($quiet->fresh()->app_competition);
        $this->assertNull($rising->fresh()->app_competition);
        $this->assertNotNull(Source::where('key', 'app_store_search')->value('last_error'));
        Http::assertSent(fn ($request) => $request['term'] === 'plant identifier');
        Http::assertNotSent(fn ($request) => $request['term'] === 'pdf scanner');
    }
}
