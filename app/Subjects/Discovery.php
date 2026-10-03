<?php

namespace App\Subjects;

use App\Models\Item;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Proposes Candidates from a day's Items (ADR-0006). Names come from what the
 * Sources themselves publish — a Stack Exchange tag, a capitalised phrase
 * repeated across Hacker News titles — never from a model.
 *
 * A Candidate is a topic that recurs — mentioned by at least the configured
 * number of Items in one day — and is two to five words long. A bare word
 * ("Apple", "python") is too broad to measure cleanly, so it is never proposed;
 * the owner can still seed one.
 */
final class Discovery
{
    private const LEADING_NOISE = ['a', 'an', 'the', 'how', 'why', 'what', 'when', 'i', 'my', 'we', 'our', 'you', 'your', 'is', 'it', 'its', 'this', 'new', 'show', 'ask', 'launch', 'tell', 'hn', 'in', 'on', 'of', 'for', 'to', 'and', 'with', 'from'];

    /**
     * @param  Collection<int, Item>  $items  with their Source loaded
     * @return array<string, list<string>> every Candidate name => up to three titles it was seen in, most mentioned first
     */
    public function candidates(Collection $items): array
    {
        $seen = [];

        foreach ($items as $item) {
            $mentioned = [];

            foreach ($this->mentions($item) as $name => $weight) {
                $slug = Subject::slugFor((string) $name);
                $mentioned[$slug] = max($mentioned[$slug] ?? 0, $weight);
                $seen[$slug] ??= ['name' => (string) $name, 'weight' => 0, 'titles' => []];
                $seen[$slug]['titles'][$item->title] = true;
            }

            foreach ($mentioned as $slug => $weight) {
                $seen[$slug]['weight'] += $weight;
            }
        }

        return collect($seen)
            ->reject(fn (array $candidate, string $slug) => $slug === '' || $candidate['weight'] < config('trend.discovery.min_mentions'))
            ->sortByDesc('weight')
            ->mapWithKeys(fn (array $candidate) => [$candidate['name'] => array_slice(array_keys($candidate['titles']), 0, 3)])
            ->all();
    }

    /**
     * What one Item mentions; a name counts once per Item.
     *
     * @return array<string, int>
     */
    private function mentions(Item $item): array
    {
        return match ($item->source->key) {
            'stack_exchange' => array_fill_keys($this->topics($this->tags($item)), 1),
            'hacker_news' => array_fill_keys($this->topics($this->phrases($item->title)), 1),
            default => [],
        };
    }

    /**
     * Only names of two to five words are topics; a bare word is too broad.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function topics(array $names): array
    {
        return array_values(array_filter($names, fn (string $name) => in_array(count(explode(' ', $name)), [2, 3, 4, 5], true)));
    }

    /** @return list<string> */
    private function tags(Item $item): array
    {
        return array_values(array_filter(array_map(
            fn (string $tag) => str_replace('-', ' ', trim($tag)),
            explode(',', (string) $item->excerpt),
        )));
    }

    /** Capitalised runs of up to five words, trimmed of leading filler. @return list<string> */
    private function phrases(string $title): array
    {
        preg_match_all('/(?<![\p{L}\d])\p{Lu}[\p{L}\d.+#-]*(?:\s+\p{Lu}[\p{L}\d.+#-]*){0,4}/u', $title, $matches);

        return collect($matches[0])
            ->map(function (string $phrase) {
                $words = preg_split('/\s+/', $phrase);

                while ($words !== [] && in_array(Str::lower($words[0]), self::LEADING_NOISE, true)) {
                    array_shift($words);
                }

                return rtrim(implode(' ', $words), '.');
            })
            ->filter(fn (string $phrase) => mb_strlen($phrase) > 1)
            ->unique()
            ->values()
            ->all();
    }
}
