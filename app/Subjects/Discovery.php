<?php

namespace App\Subjects;

use App\Models\Item;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Proposes Candidates from a day's Items (ADR-0006). Names come from what the
 * Sources themselves publish — a Stack Exchange tag, a capitalised phrase
 * repeated across Hacker News titles, an App Store search term — never from a model.
 *
 * A Candidate is two to five words long and either recurs — mentioned by at
 * least the configured number of Items in one day — or is an App Store search
 * term that held its genre's top list two weeks running after climbing it
 * (ADR-0011, ADR-0012). A bare
 * word ("Apple", "python") is too broad to measure cleanly, so it is never
 * proposed; the owner can still seed one.
 */
final class Discovery
{
    private const LEADING_NOISE = ['a', 'an', 'the', 'how', 'why', 'what', 'when', 'i', 'my', 'we', 'our', 'you', 'your', 'is', 'it', 'its', 'this', 'new', 'show', 'ask', 'launch', 'tell', 'hn', 'in', 'on', 'of', 'for', 'to', 'and', 'with', 'from'];

    /**
     * @param  Collection<int, Item>  $items  with their Source loaded
     * @return array<string, array{seen_in: list<string>, labels: list<string>, climb: ?int}> every Candidate name => up to three titles it was seen in, the Labels its Source gives it, and for an App Store term its climb; most mentioned first
     */
    public function candidates(Collection $items): array
    {
        $seen = $this->climbers($items->filter(fn (Item $item) => $item->source->key === 'apple_ads'));

        foreach ($items as $item) {
            $mentioned = [];

            foreach ($this->mentions($item) as $name => $weight) {
                $slug = Subject::slugFor((string) $name);
                $mentioned[$slug] = max($mentioned[$slug] ?? 0, $weight);
                $seen[$slug] ??= ['name' => (string) $name, 'weight' => 0, 'titles' => [], 'labels' => [], 'climb' => null];
                $seen[$slug]['titles'][$item->title] = true;
            }

            foreach ($mentioned as $slug => $weight) {
                $seen[$slug]['weight'] += $weight;
            }
        }

        return collect($seen)
            ->reject(fn (array $candidate, string $slug) => $slug === '' || $candidate['weight'] < config('trend.discovery.min_mentions'))
            ->sortByDesc('weight')
            ->mapWithKeys(fn (array $candidate) => [$candidate['name'] => [
                'seen_in' => array_slice(array_keys($candidate['titles']), 0, 3),
                'labels' => array_keys($candidate['labels']),
                'climb' => $candidate['climb'],
            ]])
            ->all();
    }

    /**
     * App Store search terms ranked in their genre's top list this week and last
     * that climbed it by at least the configured places since four weeks earlier —
     * a newcomer counted from just below the list — the biggest climbs first
     * (ADR-0012). Each counts as
     * recurring and carries its genre; Intake caps how many a week become Subjects.
     *
     * @param  Collection<int, Item>  $items
     * @return array<string, array{name: string, weight: int, titles: array<string, bool>, labels: array<string, bool>, climb: int}>
     */
    private function climbers(Collection $items): array
    {
        $config = config('trend.apple_ads');
        $names = $climbs = $titles = $labels = [];

        foreach ($items as $item) {
            if (! preg_match('/^(\w+), rank (\d+), last week (\d+|below \d+), four weeks ago (?:(\d+)|below (\d+))$/', (string) $item->excerpt, $match)
                || $this->topics([$item->title]) === []) {
                continue;
            }

            // A one-week jump is not a Candidate: the term must have held its place last week too (ADR-0012).
            if (! ctype_digit($match[3])) {
                continue;
            }

            // A term new to the list since four weeks earlier climbed at least from just below it.
            $ranked = ($match[4] ?? '') !== '';
            $before = $ranked ? (int) $match[4] : (int) $match[5] + 1;
            $climb = $before - (int) $match[2];

            if ($climb < $config['min_climb']) {
                continue;
            }

            $slug = Subject::slugFor($item->title);
            $genre = Str::slug(Str::lower($match[1]));
            $names[$slug] = $item->title;
            $climbs[$slug] = max($climbs[$slug] ?? PHP_INT_MIN, $climb);
            $titles[$slug]["App Store search in {$genre}: rank {$match[2]}, last week {$match[3]}, four weeks ago ".($ranked ? $before : "below {$match[5]}")] = true;
            $labels[$slug][$genre] = true;
        }

        arsort($climbs);
        $climbers = [];

        foreach ($climbs as $slug => $climb) {
            $climbers[$slug] = ['name' => $names[$slug], 'weight' => (int) config('trend.discovery.min_mentions'), 'titles' => $titles[$slug], 'labels' => $labels[$slug], 'climb' => $climb];
        }

        return $climbers;
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
            default => [], // apple_ads proposes through its climbers, never by mentions
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
