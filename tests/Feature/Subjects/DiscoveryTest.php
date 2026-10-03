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

        $this->assertSame([], array_keys($candidates), 'svelte-kit is on two questions only, and a bare tag like svelte is too broad');

        $this->items('stack_exchange', [['SSR in svelte-kit', 'svelte-kit']], offset: 10);

        $this->assertSame(['svelte kit'], array_keys($this->candidates()), 'a two-word tag on three questions');
    }

    public function test_one_question_is_one_mention(): void
    {
        $this->items('stack_exchange', [
            ['Pointers', 'react-native, react-native-web, react-native'],
            ['More pointers', 'react-native-web'],
            ['Templates', 'react-native-web'],
        ]);

        $this->assertSame(['react native web'], array_keys($this->candidates()));

        $this->items('stack_exchange', array_fill(0, 3, ['Routing', 'asp-net-core-web-api-routing']), offset: 10);

        $this->assertSame(['react native web'], array_keys($this->candidates()), 'six words is more than a topic');
    }

    public function test_a_single_launch_is_not_a_trend_and_bare_words_are_too_broad(): void
    {
        $this->items('hacker_news', [
            ['Show HN: Tidewave – a coding agent for Full Stack apps', null],
            ['Apple ships a phone', null],
            ['Apple sues Google', null],
            ['Apple and Full Stack hiring', null],
        ]);

        $this->assertSame([], array_keys($this->candidates()));
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
    private function items(string $source, array $rows, int $offset = 0): void
    {
        $this->seed(SourceSeeder::class);
        $sourceId = Source::where('key', $source)->value('id');

        foreach ($rows as $index => [$title, $excerpt]) {
            Item::create(['source_id' => $sourceId, 'external_id' => "{$source}-".($index + $offset), 'title' => $title, 'excerpt' => $excerpt, 'observed_on' => '2026-09-21']);
        }
    }

    /** @return array<string, list<string>> */
    private function candidates(): array
    {
        return $this->app->make(Discovery::class)->candidates(Item::with('source')->get());
    }
}
