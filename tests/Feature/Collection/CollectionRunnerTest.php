<?php

namespace Tests\Feature\Collection;

use App\Collection\CollectedItem;
use App\Collection\CollectionRunner;
use App\Collection\SourceRegistry;
use App\Models\Item;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeCollector;
use Tests\TestCase;

class CollectionRunnerTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-09-11';

    public function test_it_stores_a_sources_items_with_source_timestamp_link_quantity_and_text(): void
    {
        $source = $this->source('stack_exchange');
        $published = CarbonImmutable::parse(self::DAY.' 08:30:00', 'UTC');

        $outcome = $this->runner(
            FakeCollector::returning('stack_exchange', $this->question('80002501', 'Downloading Apple-hosted asset packs', 5, $published)),
        )->collect($source, $this->day());

        $this->assertTrue($outcome->ok());
        $this->assertSame(1, $outcome->itemCount);
        $this->assertSame(self::DAY, $outcome->day->toDateString());

        $item = Item::query()->sole();
        $this->assertSame($source->id, $item->source_id);
        $this->assertSame('80002501', $item->external_id);
        $this->assertSame('Downloading Apple-hosted asset packs', $item->title);
        $this->assertSame('tags', $item->excerpt);
        $this->assertSame('https://stackoverflow.com/questions/80002501', $item->url);
        $this->assertTrue($item->published_at->equalTo($published), 'the instant the Source published it survives storage');
        $this->assertSame(self::DAY, $item->observed_on->toDateString());
        $this->assertSame(5, $item->signal);
    }

    public function test_a_successful_collection_records_health_on_the_source(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-12 06:30:00', 'Europe/Istanbul'));

        $source = $this->source('stack_exchange');

        $this->runner(
            FakeCollector::returning('stack_exchange', $this->question('q-1'), $this->question('q-2')),
        )->collect($source, $this->day());

        $source->refresh();
        $this->assertSame('2026-09-12 06:30', $source->last_run_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-12 06:30', $source->last_success_at->format('Y-m-d H:i'));
        $this->assertSame(2, $source->last_item_count);
        $this->assertNull($source->last_error);
    }

    public function test_re_running_the_same_day_changes_nothing(): void
    {
        $source = $this->source('stack_exchange');
        $runner = $this->runner(
            FakeCollector::returning('stack_exchange', $this->question('q-1'), $this->question('q-2', signal: 3)),
        );

        $runner->collect($source, $this->day());
        $before = Item::query()->orderBy('id')->get()->toArray();

        $runner->collect($source, $this->day());

        $this->assertSame(2, Item::query()->count(), 're-running never duplicates');
        $this->assertSame($before, Item::query()->orderBy('id')->get()->toArray());
    }

    public function test_re_running_the_same_day_replaces_the_items_that_are_no_longer_published(): void
    {
        $source = $this->source('stack_exchange');
        $day = $this->day();

        $this->runner(
            FakeCollector::returning('stack_exchange', $this->question('q-1'), $this->question('q-2', 'Old title')),
        )->collect($source, $day);

        $this->runner(
            FakeCollector::returning('stack_exchange', $this->question('q-2', 'New title'), $this->question('q-3')),
        )->collect($source, $day);

        $this->assertSame(2, Item::query()->count());
        $this->assertSame(['q-2', 'q-3'], Item::query()->orderBy('external_id')->pluck('external_id')->all());
        $this->assertSame('New title', Item::query()->where('external_id', 'q-2')->value('title'));
    }

    public function test_a_failing_source_keeps_its_previously_collected_items_and_records_the_failure(): void
    {
        $source = $this->source('stack_exchange');
        $day = $this->day();

        $this->runner(FakeCollector::returning('stack_exchange', $this->question('q-1')))->collect($source, $day);
        $successAt = $source->refresh()->last_success_at;

        $outcome = $this->runner(FakeCollector::failing('stack_exchange', 'the Source is down'))->collect($source, $day);

        $this->assertFalse($outcome->ok());
        $this->assertSame('the Source is down', $outcome->error);

        $this->assertSame(1, Item::query()->count(), 'a failed run leaves previously collected Items untouched');
        $this->assertSame('q-1', Item::query()->value('external_id'));

        $source->refresh();
        $this->assertSame('the Source is down', $source->last_error);
        $this->assertSame(1, $source->last_item_count, 'the last success still reports its count');
        $this->assertTrue($source->last_success_at->equalTo($successAt));
        $this->assertNotNull($source->last_run_at);
    }

    public function test_one_failing_source_does_not_stop_the_others(): void
    {
        $this->seed(SourceSeeder::class);
        $good = Source::query()->where('key', 'stack_exchange')->firstOrFail();
        $bad = Source::query()->where('key', 'hacker_news')->firstOrFail();

        $outcomes = $this->runner(
            FakeCollector::returning('stack_exchange', $this->question('q-1')),
            FakeCollector::failing('hacker_news'),
        )->collectAll([$bad, $good], $this->day());

        $this->assertFalse($outcomes['hacker_news']->ok());
        $this->assertTrue($outcomes['stack_exchange']->ok());
        $this->assertSame(1, Item::query()->count());
    }

    public function test_a_source_with_no_collector_fails_without_touching_items(): void
    {
        $source = $this->source('hacker_news');

        $outcome = $this->runner(FakeCollector::returning('stack_exchange'))->collect($source, $this->day());

        $this->assertFalse($outcome->ok());
        $this->assertStringContainsString('hacker_news', $outcome->error);
        $this->assertSame(0, Item::query()->count());
        $this->assertStringContainsString('hacker_news', $source->refresh()->last_error);
    }

    private function source(string $key): Source
    {
        $this->seed(SourceSeeder::class);

        return Source::query()->where('key', $key)->firstOrFail();
    }

    private function runner(FakeCollector ...$collectors): CollectionRunner
    {
        $registry = new SourceRegistry(
            collect($collectors)->keyBy(fn (FakeCollector $collector) => $collector->key())->all()
        );

        return new CollectionRunner($registry);
    }

    private function day(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::DAY, 'UTC');
    }

    private function question(string $id, string $title = 'A question', int $signal = 0, ?CarbonImmutable $publishedAt = null): CollectedItem
    {
        return new CollectedItem(
            externalId: $id,
            title: $title,
            excerpt: 'tags',
            url: "https://stackoverflow.com/questions/{$id}",
            publishedAt: $publishedAt ?? CarbonImmutable::parse(self::DAY.' 08:30:00', 'UTC'),
            signal: $signal,
        );
    }
}
