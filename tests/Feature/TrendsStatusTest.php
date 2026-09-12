<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Source;
use App\Models\Subject;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendsStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_the_registered_sources_and_stored_counts(): void
    {
        $this->seed(SourceSeeder::class);
        Subject::create([
            'name' => 'AI note taking',
            'slug' => 'ai-note-taking',
            'query' => 'AI note taking',
            'first_seen_on' => '2026-09-01',
        ]);
        Item::create([
            'source_id' => Source::where('key', 'reddit')->value('id'),
            'external_id' => 'post-1',
            'title' => 'Anything better than Notion for AI notes?',
            'observed_on' => '2026-09-01',
        ]);

        $this->artisan('trends:status')
            ->expectsOutputToContain('trend-analyzer')
            ->expectsOutputToContain('reddit')
            ->expectsOutputToContain('subjects')
            ->assertSuccessful();
    }

    public function test_it_tells_you_when_no_sources_are_registered(): void
    {
        $this->artisan('trends:status')
            ->expectsOutputToContain('No Sources registered')
            ->assertSuccessful();
    }
}
