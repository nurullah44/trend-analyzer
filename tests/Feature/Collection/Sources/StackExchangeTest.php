<?php

namespace Tests\Feature\Collection\Sources;

use App\Collection\SourceCollector;
use App\Collection\Sources\StackExchange;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures;
use Tests\TestCase;
use UnexpectedValueException;

class StackExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_implements_the_source_contract(): void
    {
        $collector = $this->app->make(StackExchange::class);

        $this->assertInstanceOf(SourceCollector::class, $collector);
        $this->assertSame('stack_exchange', $collector->key());
    }

    public function test_it_collects_a_recorded_day_of_questions(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.stackexchange.com/*' => Http::response(Fixtures::json('StackExchange/2026-09-11-page-1.json'))]);

        $collected = $this->app->make(StackExchange::class)
            ->collectForDay($this->source(), CarbonImmutable::parse('2026-09-11', 'UTC'));

        $this->assertSame('2026-09-11', $collected->day->toDateString());
        $this->assertCount(49, $collected->items, 'every question in the fixture is kept');

        $first = $collected->items[0];
        $this->assertSame('80002501', $first->externalId);
        $this->assertSame('Downloading Apple-hosted asset packs in a SwiftUI macOS app', $first->title);
        $this->assertSame('swift, xcode, macos, swiftui, background-assets-framework', $first->excerpt, 'the question’s tags are its text beyond the title');
        $this->assertSame('https://stackoverflow.com/questions/80002501/downloading-apple-hosted-asset-packs-in-a-swiftui-macos-app', $first->url);
        $this->assertTrue($first->publishedAt->equalTo(CarbonImmutable::createFromTimestampUTC(1789164382)));
        $this->assertSame(-2, $first->signal, 'the measured quantity is the score the Source reports');

        $upvoted = collect($collected->items)->firstWhere('externalId', '80002473');
        $this->assertSame(2, $upvoted->signal);
    }

    public function test_it_asks_for_the_whole_utc_day_and_nothing_else(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.stackexchange.com/*' => Http::response(Fixtures::json('StackExchange/2026-09-11-page-1.json'))]);

        $this->app->make(StackExchange::class)
            ->collectForDay($this->source(), CarbonImmutable::parse('2026-09-11', 'UTC'));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $query = $request->data();

            return str_starts_with($request->url(), 'https://api.stackexchange.com/2.3/questions')
                && $query['site'] === 'stackoverflow'
                && (int) $query['fromdate'] === 1789084800
                && (int) $query['todate'] === 1789171199
                && $query['sort'] === 'creation'
                && $query['order'] === 'desc'
                && (int) $query['pagesize'] === 100
                && (int) $query['page'] === 1;
        });
    }

    public function test_it_follows_pages_until_the_source_has_no_more(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.stackexchange.com/*' => Http::sequence()
                ->push(Fixtures::json('StackExchange/2026-08-01-page-1.json'))
                ->push(Fixtures::json('StackExchange/2026-08-01-page-2.json')),
        ]);

        $collected = $this->app->make(StackExchange::class)
            ->collectForDay($this->source(), CarbonImmutable::parse('2026-08-01', 'UTC'));

        $this->assertCount(19, $collected->items);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => (int) $request->data()['page'] === 2);
    }

    public function test_a_response_without_items_is_an_error_not_an_empty_day(): void
    {
        Http::fake(['api.stackexchange.com/*' => Http::response(['has_more' => false, 'quota_remaining' => 10])]);

        $this->expectException(UnexpectedValueException::class);

        $this->app->make(StackExchange::class)
            ->collectForDay($this->source(), CarbonImmutable::parse('2026-09-11', 'UTC'));
    }

    public function test_a_refused_request_fails_the_run_instead_of_returning_an_empty_day(): void
    {
        Http::fake(['api.stackexchange.com/*' => Http::response(['error_id' => 502, 'error_message' => 'throttle violation'], 400)]);

        $this->expectException(RequestException::class);

        try {
            $this->app->make(StackExchange::class)
                ->collectForDay($this->source(), CarbonImmutable::parse('2026-09-11', 'UTC'));
        } finally {
            Http::assertSentCount(1);
        }
    }

    private function source(): Source
    {
        $this->seed(SourceSeeder::class);

        return Source::query()->where('key', 'stack_exchange')->firstOrFail();
    }
}
