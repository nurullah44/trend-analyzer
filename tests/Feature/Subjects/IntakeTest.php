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

    public function test_an_app_store_candidate_carries_its_genre_beside_the_classifiers_label(): void
    {
        config(['trend.classifier.key' => 'jv_test']);
        Item::create(['source_id' => Source::where('key', 'apple_ads')->value('id'), 'external_id' => 'HEALTH_FITNESS:step counter', 'title' => 'step counter', 'excerpt' => 'HEALTH_FITNESS, rank 3, last week 250, four weeks ago 250', 'observed_on' => '2026-09-21']);
        Http::fake(['api.typesafe.ai/v1/systemone' => Http::response(['answers' => [
            'need' => ['type' => 'noul', 'noul' => 0.9],
            'label' => ['type' => 'choice', 'choice' => 'mobile-app'],
        ]])]);

        $this->discover();

        $this->assertSame(['mobile-app', 'health-fitness'], Subject::where('slug', 'step-counter')->first()->labels->pluck('name')->all());
        Http::assertSent(fn (Request $request) => $request['state']['candidate'] === 'step counter'
            && $request['state']['seen_in'] === ['App Store search in health-fitness: rank 3, last week 250, four weeks ago 250']
            && ! isset($request['questions']['specific'])
            && str_contains($request['questions']['need']['instructions'], 'kind of app'));
    }

    public function test_known_names_never_use_up_the_weekly_app_store_cap_and_a_rerun_never_goes_past_it(): void
    {
        config(['trend.classifier.key' => null, 'trend.apple_ads.max_candidates' => 1]);
        $apple = Source::where('key', 'apple_ads')->value('id');
        foreach ([['step counter', 'rank 3, last week 9, four weeks ago 250'], ['sleep tracker', 'rank 5, last week 9, four weeks ago below 500'], ['water reminder', 'rank 9, last week 9, four weeks ago 400']] as [$term, $ranks]) {
            Item::create(['source_id' => $apple, 'external_id' => "HEALTH_FITNESS:{$term}", 'title' => $term, 'excerpt' => "HEALTH_FITNESS, {$ranks}", 'observed_on' => '2026-09-21']);
        }
        $this->app->make(Intake::class)->seed('Sleep Tracker');

        $this->discover();
        $this->discover();

        $this->assertTrue(Subject::where('slug', 'water-reminder')->exists(), 'the biggest fresh climb, past the known one');
        $this->assertFalse(Subject::where('slug', 'step-counter')->exists(), 'one a week, however often the day is discovered');
        $this->assertSame(391, Subject::where('slug', 'water-reminder')->first()->events->first()->payload['climb']);
    }

    public function test_the_app_store_cap_takes_the_biggest_climb_even_when_another_site_mentions_a_smaller_one(): void
    {
        config(['trend.classifier.key' => null, 'trend.apple_ads.max_candidates' => 1]);
        $apple = Source::where('key', 'apple_ads')->value('id');
        Item::create(['source_id' => $apple, 'external_id' => 'HEALTH_FITNESS:step counter', 'title' => 'step counter', 'excerpt' => 'HEALTH_FITNESS, rank 3, last week 3, four weeks ago 104', 'observed_on' => '2026-09-21']);
        Item::create(['source_id' => $apple, 'external_id' => 'HEALTH_FITNESS:water reminder', 'title' => 'water reminder', 'excerpt' => 'HEALTH_FITNESS, rank 9, last week 9, four weeks ago 900', 'observed_on' => '2026-09-21']);
        foreach (['Step Counter apps', 'Why Step Counter'] as $index => $title) {
            Item::create(['source_id' => Source::where('key', 'hacker_news')->value('id'), 'external_id' => "sc-{$index}", 'title' => $title, 'observed_on' => '2026-09-21']);
        }

        $this->discover();

        $this->assertTrue(Subject::where('slug', 'water-reminder')->exists());
        $this->assertFalse(Subject::where('slug', 'step-counter')->exists());
    }

    public function test_app_store_searches_for_one_brand_or_event_are_archived_and_take_no_place(): void
    {
        config(['trend.classifier.key' => 'jv_test', 'trend.apple_ads.max_candidates' => 1, 'trend.apple_ads.max_classified' => 3]);
        $apple = Source::where('key', 'apple_ads')->value('id');
        $terms = ['chicago marathon' => [5, 0.05], 'ufc fight pass' => [6, 0.1], 'ai maker' => [7, 0.9], 'pdf scanner' => [8, 0.95]];
        foreach ($terms as $term => [$rank]) {
            Item::create(['source_id' => $apple, 'external_id' => "HEALTH_FITNESS:{$term}", 'title' => $term, 'excerpt' => "HEALTH_FITNESS, rank {$rank}, last week {$rank}, four weeks ago below 500", 'observed_on' => '2026-09-21']);
        }
        Http::fake(['api.typesafe.ai/v1/systemone' => fn (Request $request) => Http::response(['answers' => [
            'need' => ['type' => 'noul', 'noul' => $terms[$request['state']['candidate']][1]],
            'label' => ['type' => 'choice', 'choice' => 'mobile-app'],
        ]])]);

        $outcomes = $this->discover();
        $this->discover();

        $this->assertSame(2, $outcomes['archived']);
        $this->assertSame(SubjectState::Archived, Subject::where('slug', 'chicago-marathon')->first()->state);
        $this->assertSame(SubjectState::Watching, Subject::where('slug', 'ai-maker')->first()->state, 'the places go to the next climber');
        $this->assertSame(0.9, Subject::where('slug', 'ai-maker')->first()->events->first()->payload['need']);
        $this->assertFalse(Subject::where('slug', 'pdf-scanner')->exists(), 'the week\'s one place is filled');
    }

    public function test_at_most_the_configured_number_of_climbers_are_put_to_the_classifier(): void
    {
        config(['trend.classifier.key' => 'jv_test', 'trend.apple_ads.max_classified' => 2]);
        $apple = Source::where('key', 'apple_ads')->value('id');
        foreach (['chicago marathon', 'ufc fight pass', 'game informer'] as $index => $term) {
            Item::create(['source_id' => $apple, 'external_id' => "SPORTS:{$term}", 'title' => $term, 'excerpt' => 'SPORTS, rank '.($index + 1).', last week 9, four weeks ago below 500', 'observed_on' => '2026-09-21']);
        }
        Http::fake(['api.typesafe.ai/v1/systemone' => fn (Request $request) => Http::response(['answers' => [
            isset($request['questions']['need']) ? 'need' : 'specific' => ['type' => 'noul', 'noul' => 0.05],
            'label' => ['type' => 'choice', 'choice' => 'other'],
        ]])]);

        $this->discover();
        $this->discover();

        $this->assertSame(2, Subject::whereNotIn('slug', ['tide-wave', 'blob-store', 'programming-languages'])->count());
    }

    public function test_app_store_subjects_proposed_before_the_need_question_are_asked_it_once(): void
    {
        config(['trend.classifier.key' => 'jv_test']);
        $subject = fn (string $name, SubjectState $state, array $payload) => tap(Subject::create(['name' => $name, 'slug' => Subject::slugFor($name), 'query' => $name, 'state' => $state, 'first_seen_on' => '2026-09-28']),
            fn (Subject $subject) => $subject->events()->create(['type' => 'discovered', 'to_state' => $state->value, 'payload' => $payload, 'happened_at' => now()]));
        $subject('willow tv', SubjectState::Watching, ['seen_in' => ['App Store search in sports: rank 302, last week below 500'], 'specific' => 0.95, 'climb' => 302]);
        $subject('ai maker', SubjectState::Backlog, ['seen_in' => [], 'specific' => 0.56, 'climb' => 269]);
        $subject('photos to pdf', SubjectState::Watching, ['seen_in' => [], 'specific' => 0.82, 'climb' => 333]);
        $subject('Tidewave', SubjectState::Watching, ['seen_in' => [], 'specific' => 0.9]);
        $risen = $subject('chicago marathon', SubjectState::Watching, ['seen_in' => [], 'specific' => 0.82, 'climb' => 306]);
        $risen->moveTo(SubjectState::Rising, 'accelerating');
        $risen->moveTo(SubjectState::Watching, 'test');
        $subject('golf channel app', SubjectState::Watching, ['seen_in' => [], 'specific' => 0.92, 'climb' => 407]);
        $this->app->make(Intake::class)->seed('golf channel app', 'golf channel');
        $need = ['willow tv' => 0.05, 'ai maker' => 0.92, 'photos to pdf' => 0.9, 'golf channel app' => 0.05];
        Http::fake(['api.typesafe.ai/v1/systemone' => fn (Request $request) => Http::response(['answers' => [
            'need' => ['type' => 'noul', 'noul' => $need[$request['state']['candidate']]],
            'label' => ['type' => 'choice', 'choice' => 'mobile-app'],
        ]])]);

        $intake = $this->app->make(Intake::class);

        $this->assertSame(['willow-tv' => 'archived', 'ai-maker' => 'watching'], $intake->reclassify());
        $this->assertSame([], $intake->reclassify(), 'asked once');
        Http::assertSentCount(3);
        $this->assertSame(SubjectState::Watching, Subject::where('slug', 'golf-channel-app')->first()->state, 'the owner seeded it: never overruled');
        $this->assertSame('a search for one brand, app or event', Subject::where('slug', 'willow-tv')->first()->events()->latest('id')->first()->reason);
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
