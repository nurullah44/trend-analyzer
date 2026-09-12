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
 * day's Items and recording the Source's health. A failing Source never stops
 * another one, and never loses what an earlier run already collected.
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
     * Upsert the day's Items for the Source: one Item per Source per day per
     * external id, updated in place on a re-run and never duplicated. Nothing
     * is written until the whole day arrived.
     */
    private function store(Source $source, CollectedDay $collected): void
    {
        $observedOn = $collected->day->toDateString();

        DB::transaction(function () use ($source, $collected, $observedOn) {
            foreach ($collected->items as $item) {
                $this->record($source, $observedOn, $item);
            }
        });
    }

    private function record(Source $source, string $observedOn, CollectedItem $item): void
    {
        Item::updateOrCreate(
            [
                'source_id' => $source->id,
                'observed_on' => $observedOn,
                'external_id' => $item->externalId,
            ],
            [
                'title' => $item->title,
                'excerpt' => $item->excerpt,
                'url' => $item->url,
                // Timestamps are read back in the app timezone, so store them there; the instant is what matters.
                'published_at' => $item->publishedAt?->setTimezone(config('app.timezone')),
                'signal' => $item->signal,
            ],
        );
    }
}
