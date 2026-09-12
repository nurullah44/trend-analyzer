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
        $this->assertTrue(Source::where('key', 'reddit')->first()->enabled);
        $this->assertFalse(Source::where('key', 'x_trends')->first()->enabled);

        Source::where('key', 'reddit')->update(['last_item_count' => 12]);
        $this->seed(SourceSeeder::class);

        $this->assertSame(12, Source::where('key', 'reddit')->first()->last_item_count);
    }
}
