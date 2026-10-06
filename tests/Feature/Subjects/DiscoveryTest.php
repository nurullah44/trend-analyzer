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

    public function test_app_store_terms_that_held_their_place_after_climbing_their_genres_top_list_are_candidates_with_their_genre(): void
    {
        $this->items('apple_ads', [
            ['pdf scanner', 'PRODUCTIVITY_UTILITIES, rank 2, last week 3, four weeks ago 4'],
            ['step counter', 'HEALTH_FITNESS, rank 3, last week 250, four weeks ago 420'],
            ['ai note taker', 'PRODUCTIVITY_UTILITIES, rank 40, last week 120, four weeks ago below 500'],
            ['ai note taker', 'BUSINESS, rank 90, last week 300, four weeks ago below 500'],
            ['chicago marathon', 'HEALTH_FITNESS, rank 5, last week below 500, four weeks ago below 500'],
            ['chatgpt', 'PRODUCTIVITY_UTILITIES, rank 1, last week 1, four weeks ago below 500'],
        ]);

        $candidates = $this->candidates();

        $this->assertSame(['ai note taker', 'step counter'], array_keys($candidates), 'a newcomer counts from just below the list, biggest climb first; a one-week jump, a bare word and a small climb are not Candidates');
        $this->assertSame(461, $candidates['ai note taker']['climb']);
        $this->assertSame(['productivity-utilities', 'business'], $candidates['ai note taker']['labels'], 'the genre rides along as a Label');
        $this->assertSame(['App Store search in productivity-utilities: rank 40, last week 120, four weeks ago below 500', 'App Store search in business: rank 90, last week 300, four weeks ago below 500'], $candidates['ai note taker']['seen_in']);
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

    /** @return array<string, array{seen_in: list<string>, labels: list<string>, climb: ?int}> */
    private function candidates(): array
    {
        return $this->app->make(Discovery::class)->candidates(Item::with('source')->get());
    }
}
