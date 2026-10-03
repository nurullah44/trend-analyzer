<?php

namespace Tests\Feature\Collection\Sources;

use App\Collection\Sources\HackerNews;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures;
use Tests\TestCase;
use UnexpectedValueException;

class HackerNewsTest extends TestCase
{
    public function test_it_collects_a_recorded_day_in_four_windows(): void
    {
        Http::preventStrayRequests();
        Http::fake(['hn.algolia.com/api/v1/search_by_date*' => Http::sequence()
            ->push(Fixtures::json('HackerNews/2026-09-21-window-0.json'))
            ->push(Fixtures::json('HackerNews/2026-09-21-window-1.json'))
            ->push(Fixtures::json('HackerNews/2026-09-21-window-2.json'))
            ->push(Fixtures::json('HackerNews/2026-09-21-window-3.json'))]);

        $day = $this->app->make(HackerNews::class)->collectForDay(new Source, CarbonImmutable::parse('2026-09-21', 'UTC'));

        $this->assertSame(151 + 212 + 371 + 288, $day->count());
        $first = $day->items[0];
        $this->assertSame('49783513', $first->externalId);
        $this->assertSame("Postgres 19: What's new in monitoring?", $first->title);
        $this->assertSame(2, $first->measuredQuantity, 'points are the measured quantity');
        $this->assertTrue($first->publishedAt->equalTo(CarbonImmutable::createFromTimestampUTC(1789970377)));

        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request) => $request->data()['numericFilters'] === 'created_at_i>=1789948800,created_at_i<1789970400');
        Http::assertSent(fn (Request $request) => $request->data()['numericFilters'] === 'created_at_i>=1790013600,created_at_i<1790035200');
    }

    public function test_a_window_that_would_lose_stories_fails_the_run(): void
    {
        Http::fake(['hn.algolia.com/*' => Http::response(['hits' => [], 'nbHits' => 1200])]);

        $this->expectException(UnexpectedValueException::class);

        $this->app->make(HackerNews::class)->collectForDay(new Source, CarbonImmutable::parse('2026-09-21', 'UTC'));
    }

    public function test_it_measures_a_quoted_query_over_one_week(): void
    {
        Http::preventStrayRequests();
        Http::fake(['hn.algolia.com/api/v1/search?*' => Http::response(Fixtures::json('HackerNews/volume-svelte-2026-09-21.json'))]);

        $volume = $this->app->make(HackerNews::class)->volume('svelte', CarbonImmutable::parse('2026-09-21', 'UTC'));

        $this->assertSame(3, $volume);
        Http::assertSent(fn (Request $request) => $request->data()['query'] === '"svelte"'
            && $request->data()['numericFilters'] === 'created_at_i>=1789948800,created_at_i<1790553600'
            && (int) $request->data()['hitsPerPage'] === 0);
    }
}
