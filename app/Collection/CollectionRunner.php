<?php

namespace App\Collection;

use App\Models\Item;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs collectors and owns everything that happens after a fetch: storing the
 * day's Items, replacing the day's previous set, and recording the Source's
 * health. A failing Source never stops another one, and never loses what an
 * earlier run already collected.
 */
final class CollectionRunner
{
    public function __construct(private readonly SourceRegistry $sources) {}

    public function collect(Source $source, CarbonImmutable $day): CollectionOutcome
    {
        $day = $day->utc()->startOfDay();

        $source->update(['last_run_at' => now()]);

        try {
            $collected = $this->sources->for($source->key)->collectForDay($source, $day);

            $this->store($source, $collected);

            $source->update([
                'last_success_at' => now(),
                'last_item_count' => $collected->count(),
                'last_error' => null,
            ]);

            return CollectionOutcome::success($source->key, $collected->day, $collected->count());
        } catch (Throwable $e) {
            $source->update(['last_error' => Str::limit($e->getMessage(), 500)]);

            return CollectionOutcome::failure($source->key, $day, $e->getMessage());
        }
    }

    /**
     * Collect every given Source, whatever happens to any one of them.
     *
     * @param  iterable<Source>  $sources
     * @return array<string, CollectionOutcome>
     */
    public function collectAll(iterable $sources, CarbonImmutable $day): array
    {
        $outcomes = [];

        foreach ($sources as $source) {
            $outcomes[$source->key] = $this->collect($source, $day);
        }

        return $outcomes;
    }

    /**
     * Replace the Source's Items for the collected day with what the Source now
     * says it published: upsert by the Source's own identifier, and drop what it
     * no longer reports. Nothing is written until the whole day arrived.
     */
    private function store(Source $source, CollectedDay $collected): void
    {
        $observedOn = $collected->day->toDateString();
        $externalIds = array_map(fn (CollectedItem $item) => $item->externalId, $collected->items);

        DB::transaction(function () use ($source, $collected, $observedOn, $externalIds) {
            Item::query()
                ->where('source_id', $source->id)
                ->whereDate('observed_on', $observedOn)
                ->when($externalIds !== [], fn ($query) => $query->whereNotIn('external_id', $externalIds))
                ->delete();

            foreach ($collected->items as $item) {
                $this->record($source, $observedOn, $item);
            }
        });
    }

    private function record(Source $source, string $observedOn, CollectedItem $item): void
    {
        Item::updateOrCreate(
            ['source_id' => $source->id, 'external_id' => $item->externalId],
            [
                'title' => $item->title,
                'excerpt' => $item->excerpt,
                'url' => $item->url,
                // Timestamps are read back in the app timezone, so store them there; the instant is what matters.
                'published_at' => $item->publishedAt?->setTimezone(config('app.timezone')),
                'observed_on' => $observedOn,
                'signal' => $item->signal,
            ],
        );
    }
}
