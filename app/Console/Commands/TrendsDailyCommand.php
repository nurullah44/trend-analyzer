<?php

namespace App\Console\Commands;

use App\Collection\CollectionRunner;
use App\Collection\SourceRegistry;
use App\Console\Commands\Concerns\RunsAlone;
use App\Models\Item;
use App\Models\Source;
use App\Subjects\Intake;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * The daily run: collect every day of the last week a discovery Source has no
 * Items for, then propose each collected day's Candidates. A missed or
 * interrupted day heals itself.
 */
class TrendsDailyCommand extends Command
{
    use RunsAlone;

    protected $signature = 'trends:daily {--days=7 : How many days back to heal}';

    protected $description = 'Collect the missing days of the last week and discover their Candidates';

    public function handle(CollectionRunner $runner, SourceRegistry $registry, Intake $intake): int
    {
        return $this->alone(fn () => $this->heal($runner, $registry, $intake));
    }

    private function heal(CollectionRunner $runner, SourceRegistry $registry, Intake $intake): int
    {
        $sources = Source::where('enabled', true)->orderBy('key')->get()->filter(fn (Source $source) => $registry->has($source->key));
        $failed = false;

        foreach (range(max(1, (int) $this->option('days')), 1) as $back) {
            $day = CarbonImmutable::now('UTC')->subDays($back)->startOfDay();
            $missing = $sources->reject(fn (Source $source) => Item::where('source_id', $source->id)->where('observed_on', $day->toDateString())->exists());

            foreach ($runner->collectAll($missing, $day) as $outcome) {
                $failed = $failed || ! $outcome->ok();
                $this->line(sprintf('%s %s: %s', $day->toDateString(), $outcome->sourceKey, $outcome->ok() ? "{$outcome->itemCount} Items" : "failed — {$outcome->error}"));
            }

            // Discovery is idempotent, so every collected day is retried, even one an interrupted run stored but never read.
            if (Item::where('observed_on', $day->toDateString())->exists()) {
                $outcomes = $intake->discover($day);
                $this->line("{$day->toDateString()} Candidates: ".collect($outcomes)->map(fn (int $count, string $outcome) => "{$count} {$outcome}")->join(', '));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
