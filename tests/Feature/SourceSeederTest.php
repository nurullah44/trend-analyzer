<?php

namespace Tests\Feature;

use App\Models\Source;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_the_approved_sources_and_keeps_collection_health(): void
    {
        $this->seed(SourceSeeder::class);

        $this->assertSame(count(config('trend.sources')), Source::count());

        $gdelt = Source::where('key', 'gdelt')->first();
        $this->assertTrue($gdelt->enabled);
        $this->assertNull($gdelt->geo, 'a global source carries no watched geo');

        $youtube = Source::where('key', 'youtube_trending')->first();
        $this->assertSame('US', $youtube->geo, 'a per-country source records its watched geo');

        $this->assertFalse(Source::where('key', 'reddit')->first()->enabled, 'Reddit is blocked pending approval');
        $this->assertFalse(Source::where('key', 'pinterest')->first()->enabled, 'Pinterest needs a business account');

        $this->assertFalse(Source::where('key', 'x_trends')->first()->enabled, 'X is parked');

        Source::where('key', 'gdelt')->update(['last_item_count' => 12]);
        $this->seed(SourceSeeder::class);

        $this->assertSame(12, Source::where('key', 'gdelt')->first()->last_item_count);
    }
}
