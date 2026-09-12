<?php

namespace Tests\Feature\Collection;

use App\Collection\SourceRegistry;
use App\Collection\Sources\StackExchange;
use App\Collection\UnknownSourceException;
use InvalidArgumentException;
use Tests\Support\FakeCollector;
use Tests\TestCase;

class SourceRegistryTest extends TestCase
{
    public function test_it_resolves_the_approved_sources_collector_from_config(): void
    {
        $registry = $this->app->make(SourceRegistry::class);

        $this->assertTrue($registry->has('stack_exchange'));
        $this->assertInstanceOf(StackExchange::class, $registry->for('stack_exchange'));
        $this->assertSame('stack_exchange', $registry->for('stack_exchange')->key());
    }

    public function test_nothing_outside_the_approved_list_can_be_collected(): void
    {
        $approved = collect(config('trend.sources'))->pluck('key')->all();

        $this->assertEmpty(array_diff($this->app->make(SourceRegistry::class)->keys(), $approved));
    }

    public function test_a_disabled_source_has_no_collector(): void
    {
        $this->assertFalse($this->app->make(SourceRegistry::class)->has('reddit'));
    }

    public function test_an_unknown_key_is_refused(): void
    {
        $this->expectException(UnknownSourceException::class);

        $this->app->make(SourceRegistry::class)->for('tiktok');
    }

    public function test_a_collector_registered_under_the_wrong_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SourceRegistry(['stack_exchange' => FakeCollector::returning('hacker_news')]);
    }
}
