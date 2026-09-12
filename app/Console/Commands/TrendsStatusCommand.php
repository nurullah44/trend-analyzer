<?php

namespace App\Console\Commands;

use App\Models\Alarm;
use App\Models\Item;
use App\Models\Source;
use App\Models\Subject;
use Illuminate\Console\Command;

/** Reports what the analyzer currently knows: registered Sources, stored data, and collection health. */
class TrendsStatusCommand extends Command
{
    protected $signature = 'trends:status';

    protected $description = 'Show registered Sources, stored counts and collection health';

    public function handle(): int
    {
        $this->info('trend-analyzer');

        $sources = Source::orderBy('key')->get();

        if ($sources->isEmpty()) {
            $this->warn('No Sources registered. Run: php artisan db:seed --class=SourceSeeder');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Sources ('.$sources->count().')');
        $this->table(
            ['key', 'on', 'roles', 'geo', 'last run', 'last success', 'items', 'last error'],
            $sources->map(fn (Source $source) => [
                $source->key,
                $source->enabled ? 'yes' : 'no',
                $source->roles,
                $source->geo ?? 'global',
                $source->last_run_at?->format('Y-m-d H:i') ?? 'never',
                $source->last_success_at?->format('Y-m-d H:i') ?? 'never',
                $source->last_item_count ?? '—',
                $source->last_error ? str($source->last_error)->limit(40) : '—',
            ])->all()
        );

        $this->newLine();
        $this->table(['stored', 'count'], [
            ['items', Item::count()],
            ['subjects', Subject::count()],
            ['alarms', Alarm::count()],
        ]);

        if (Item::count() === 0) {
            $this->newLine();
            $this->comment('No Items collected yet — collection arrives with the next ticket.');
        }

        return self::SUCCESS;
    }
}
