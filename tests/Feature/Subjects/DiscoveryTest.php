<?php

namespace Tests\Feature\Subjects;

use App\Models\Item;
use App\Models\Source;
use App\Subjects\Discovery;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_tags_become_candidates_and_rare_ones_do_not(): void
    {
        $this->items('stack_exchange', [
            ['How do I bind in svelte?', 'svelte, javascript'],
            ['Svelte stores reset', 'svelte, svelte-kit'],
            ['Routing in svelte-kit', 'svelte, svelte-kit'],
            ['Python lists', 'python'],
        ]);

        $candidates = $this->candidates();

        $this->assertSame(['svelte'], array_keys($candidates), 'only tags on three questions or more');
        $this->assertSame(['How do I bind in svelte?', 'Svelte stores reset', 'Routing in svelte-kit'], $candidates['svelte']);
    }

    public function test_one_question_is_one_mention_and_c_plus_plus_is_not_c(): void
    {
        $this->items('stack_exchange', [
            ['Pointers', 'c, c++, c#'],
            ['More pointers', 'c++'],
            ['Templates', 'c++'],
        ]);

        $this->assertSame(['c++'], array_keys($this->candidates()));
    }

    public function test_a_show_hn_launch_is_a_candidate_on_its_own(): void
    {
        $this->items('hacker_news', [['Show HN: Tidewave – a coding agent for full-stack apps', null]]);

        $this->assertSame(['Tidewave'], array_keys($this->candidates()));
    }

    public function test_capitalised_phrases_repeated_across_titles_become_candidates(): void
    {
        $this->items('hacker_news', [
            ['The Model Context Protocol is everywhere', null],
            ['Why Model Context Protocol servers leak tokens', null],
            ['Building on Model Context Protocol', null],
            ['A lone Phrase', null],
        ]);

        $this->assertSame(['Model Context Protocol'], array_keys($this->candidates()));
    }

    /** @param list<array{0: string, 1: ?string}> $rows */
    private function items(string $source, array $rows): void
    {
        $this->seed(SourceSeeder::class);
        $sourceId = Source::where('key', $source)->value('id');

        foreach ($rows as $index => [$title, $excerpt]) {
            Item::create(['source_id' => $sourceId, 'external_id' => "{$source}-{$index}", 'title' => $title, 'excerpt' => $excerpt, 'observed_on' => '2026-09-21']);
        }
    }

    /** @return array<string, list<string>> */
    private function candidates(): array
    {
        return $this->app->make(Discovery::class)->candidates(Item::with('source')->get());
    }
}
