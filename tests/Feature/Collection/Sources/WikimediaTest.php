<?php

namespace Tests\Feature\Collection\Sources;

use App\Collection\Sources\Wikimedia;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures;
use Tests\TestCase;

class WikimediaTest extends TestCase
{
    public function test_it_sums_a_recorded_week_of_article_views(): void
    {
        Http::preventStrayRequests();
        Http::fake(['wikimedia.org/*' => Http::response(Fixtures::json('Wikimedia/svelte-2026-09-21.json'))]);

        $volume = $this->app->make(Wikimedia::class)->volumes('svelte', ['2026-09-21'])['2026-09-21'];

        $expected = array_sum(array_column(Fixtures::json('Wikimedia/svelte-2026-09-21.json')['items'], 'views'));
        $this->assertSame($expected, $volume);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/en.wikipedia/all-access/user/Svelte/daily/20260921/20260927'));
    }

    public function test_many_weeks_are_one_request(): void
    {
        Http::fake(['wikimedia.org/*' => Http::response(['items' => [
            ['timestamp' => '2026091400', 'views' => 10], ['timestamp' => '2026092000', 'views' => 5], ['timestamp' => '2026092100', 'views' => 7],
        ]])]);

        $volumes = $this->app->make(Wikimedia::class)->volumes('svelte', ['2026-09-21', '2026-09-07', '2026-09-14']);

        $this->assertSame(['2026-09-07' => 0, '2026-09-14' => 15, '2026-09-21' => 7], $volumes);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/daily/20260907/20260927'));
    }

    public function test_a_subject_without_an_article_has_no_series(): void
    {
        Http::fake(['wikimedia.org/*' => Http::response(['title' => 'Not found.'], 404)]);

        $this->assertNull($this->app->make(Wikimedia::class)->volumes('no such article', ['2026-09-21'])['2026-09-21']);
    }

    public function test_a_server_error_fails_rather_than_reading_as_zero(): void
    {
        Http::fake(['wikimedia.org/*' => Http::response('', 500)]);

        $this->expectException(RequestException::class);

        $this->app->make(Wikimedia::class)->volumes('svelte', ['2026-09-21'])['2026-09-21'];
    }
}
