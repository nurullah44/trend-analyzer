<?php

namespace Tests\Feature\Subjects;

use App\Enums\SubjectState;
use App\Models\Item;
use App\Models\Source;
use App\Models\Subject;
use App\Subjects\Intake;
use Carbon\CarbonImmutable;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SourceSeeder::class);
        $sourceId = Source::where('key', 'hacker_news')->value('id');

        $titles = [];

        foreach (['Tide Wave', 'Blob Store', 'Programming Languages'] as $topic) {
            array_push($titles, "Show HN: {$topic} – agents", "Why {$topic} matters", "{$topic} in production");
        }

        foreach ($titles as $index => $title) {
            Item::create(['source_id' => $sourceId, 'external_id' => "hn-{$index}", 'title' => $title, 'observed_on' => '2026-09-21']);
        }
    }

    public function test_the_classifier_routes_each_candidate(): void
    {
        config(['trend.classifier.key' => 'jv_test']);
        Http::preventStrayRequests();
        Http::fake(['api.typesafe.ai/v1/systemone' => function (Request $request) {
            $specific = ['Tide Wave' => 0.95, 'Blob Store' => 0.6, 'Programming Languages' => 0.1][$request['state']['candidate']];

            return Http::response(['answers' => [
                'specific' => ['type' => 'noul', 'noul' => $specific],
                'label' => ['type' => 'choice', 'choice' => 'dev-tool', 'confidence' => 0.9, 'probabilities' => ['dev-tool' => 0.9]],
            ]]);
        }]);

        $outcomes = $this->discover();

        $this->assertSame(['known' => 0, 'watching' => 1, 'backlog' => 1, 'archived' => 1], $outcomes);
        $this->assertSame(SubjectState::Watching, Subject::where('slug', 'tide-wave')->first()->state);
        $this->assertSame(SubjectState::Backlog, Subject::where('slug', 'blob-store')->first()->state);
        $this->assertSame(SubjectState::Archived, Subject::where('slug', 'programming-languages')->first()->state, 'a broad field is ruled out and remembered');
        $this->assertSame(['dev-tool'], Subject::where('slug', 'tide-wave')->first()->labels->pluck('name')->all());
        $this->assertSame(0.95, Subject::where('slug', 'tide-wave')->first()->events->first()->payload['specific']);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer jv_test')
            && $request['questions']['specific']['type'] === 'noul'
            && $request['questions']['label']['type'] === 'choice'
            && array_key_exists('dev-tool', $request['questions']['label']['criteria']));
    }

    public function test_without_a_key_nothing_calls_the_classifier_and_candidates_wait_in_backlog(): void
    {
        config(['trend.classifier.key' => null]);
        Http::fake();

        $this->assertSame(['known' => 0, 'watching' => 0, 'backlog' => 3, 'archived' => 0], $this->discover());
        Http::assertNothingSent();
    }

    public function test_a_failing_classifier_leaves_candidates_in_backlog(): void
    {
        config(['trend.classifier.key' => 'jv_test']);
        Http::fake(['api.typesafe.ai/*' => Http::response('', 503)]);

        $this->assertSame(3, $this->discover()['backlog']);
    }

    public function test_a_known_name_is_never_created_twice(): void
    {
        config(['trend.classifier.key' => null]);
        $this->discover();

        $this->assertSame(['known' => 3, 'watching' => 0, 'backlog' => 0, 'archived' => 0], $this->discover());
        $this->assertSame(3, Subject::count());
    }

    public function test_a_seed_starts_in_watching_and_promotes_a_backlog_subject(): void
    {
        $seed = $this->app->make(Intake::class)->seed('Model Context Protocol', 'MCP server');

        $this->assertSame(SubjectState::Watching, $seed->state);
        $this->assertSame('MCP server', $seed->query);

        config(['trend.classifier.key' => null]);
        $this->discover();
        $promoted = $this->app->make(Intake::class)->seed('Blob Store');

        $this->assertSame(SubjectState::Watching, $promoted->fresh()->state);
        $this->assertSame('backlog', $promoted->events()->latest('id')->first()->from_state);
    }

    public function test_known_names_do_not_use_up_the_daily_cap(): void
    {
        config(['trend.classifier.key' => null, 'trend.discovery.max_candidates' => 1]);
        $this->app->make(Intake::class)->seed('Tide Wave');

        $outcomes = $this->discover();

        $this->assertSame(1, $outcomes['known']);
        $this->assertSame(1, $outcomes['backlog']);
    }

    public function test_a_rerun_of_the_same_day_does_not_go_past_the_cap(): void
    {
        config(['trend.classifier.key' => null, 'trend.discovery.max_candidates' => 2]);

        $this->discover();
        $this->discover();

        $this->assertSame(2, Subject::count());
    }

    public function test_names_in_any_script_are_distinct_subjects(): void
    {
        $intake = $this->app->make(Intake::class);

        $this->assertNotSame($intake->seed('微信')->id, $intake->seed('抖音')->id);
        $this->expectException(\InvalidArgumentException::class);
        $intake->seed('!!!');
    }

    public function test_the_command_reports_the_outcomes(): void
    {
        config(['trend.classifier.key' => null]);

        $this->artisan('trends:discover', ['--day' => '2026-09-21'])->expectsOutputToContain('backlog')->assertSuccessful();
        $this->assertSame(3, Subject::count());
    }

    /** @return array<string, int> */
    private function discover(): array
    {
        return $this->app->make(Intake::class)->discover(CarbonImmutable::parse('2026-09-21', 'UTC'));
    }
}
