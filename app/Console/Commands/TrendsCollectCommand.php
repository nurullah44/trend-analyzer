<?php

namespace App\Console\Commands;

use App\Collection\CollectionOutcome;
use App\Collection\CollectionRunner;
use App\Collection\SourceRegistry;
use App\Console\Commands\Concerns\ReadsDay;
use App\Models\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/** Collects a day of Items from the Sources that implement the collection contract. */
class TrendsCollectCommand extends Command
{
    use ReadsDay;

    protected $signature = 'trends:collect
        {--source= : Collect one Source by key}
        {--day= : The UTC day to collect, as YYYY-MM-DD (default: yesterday)}';

    protected $description = 'Collect a day of Items from the Sources that implement the collection contract';

    public function handle(CollectionRunner $runner, SourceRegistry $sources): int
    {
        $day = $this->day();

        if ($day === null) {
            return self::FAILURE;
        }

        $selected = $this->selectedSources($sources);

        if ($selected === null) {
            return self::FAILURE;
        }

        if ($selected->isEmpty()) {
            $this->warn('No enabled Source has a collector to run.');

            return self::SUCCESS;
        }

        $this->line('Collecting for '.$day->toDateString().' (UTC)');

        $outcomes = $runner->collectAll($selected, $day);

        $this->newLine();
        $this->table(['source', 'day', 'items', 'result'], array_map(fn (CollectionOutcome $outcome) => [
            $outcome->sourceKey,
            $outcome->day->toDateString(),
            $outcome->ok() ? $outcome->itemCount : '—',
            $outcome->ok() ? 'ok' : 'failed',
        ], array_values($outcomes)));

        $failed = array_filter($outcomes, fn (CollectionOutcome $outcome) => ! $outcome->ok());

        if ($failed !== []) {
            $this->newLine();

            foreach ($failed as $outcome) {
                $this->error($outcome->sourceKey.': '.$outcome->error);
            }

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Collected %d Items from %d Sources.',
            array_sum(array_map(fn (CollectionOutcome $outcome) => $outcome->itemCount, $outcomes)),
            count($outcomes),
        ));

        return self::SUCCESS;
    }

    /**
     * The Sources to collect, or null when the request names one this app may not collect.
     *
     * @return Collection<int, Source>|null
     */
    private function selectedSources(SourceRegistry $sources): ?Collection
    {
        $key = $this->option('source');

        if ($key !== null && $key !== '') {
            return $this->oneSource($sources, $key);
        }

        $enabled = Source::query()->where('enabled', true)->orderBy('key')->get();

        $waiting = $enabled->filter(fn (Source $source) => str_contains($source->roles, 'discovery') && ! $sources->has($source->key));

        if ($waiting->isNotEmpty()) {
            $this->warn('Enabled Sources still without a collector: '.$waiting->pluck('key')->join(', '));
        }

        return $enabled->filter(fn (Source $source) => $sources->has($source->key))->values();
    }

    /** @return Collection<int, Source>|null */
    private function oneSource(SourceRegistry $sources, string $key): ?Collection
    {
        $source = Source::query()->where('key', $key)->first();

        if ($source === null) {
            $this->error("Source [{$key}] is not in the approved list (docs/sources.md).");

            return null;
        }

        if (! $source->enabled) {
            $this->error("Source [{$key}] is disabled: {$source->cost_note}.");

            return null;
        }

        if (! $sources->has($key)) {
            $this->error("Source [{$key}] does not collect (roles: {$source->roles}).");

            return null;
        }

        return collect([$source]);
    }
}
