<?php

namespace App\Console\Commands;

use App\Trends\Backtest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Would the score have caught known breakouts before their Mainstream marker, and left the duds alone? */
class TrendsBacktestCommand extends Command
{
    protected $signature = 'trends:backtest
        {query?* : Queries to replay (default: the cases in config/trend.php)}
        {--weeks=104 : How many finished weeks to replay}
        {--source=* : Only these measurement Sources}';

    protected $description = 'Replay the weekly score over history for known breakouts and duds; writes nothing';

    public function handle(Backtest $backtest): int
    {
        $to = CarbonImmutable::now('UTC')->startOfWeek()->subWeek();
        $from = $to->subWeeks(max(1, (int) $this->option('weeks')) - 1);
        $cases = $this->argument('query') ?: config('trend.backtest');
        $rows = [];

        foreach ($cases as $query) {
            $result = $backtest->replay($query, $from, $to, $this->option('source') ?: null);
            $rows[] = [$query, $result['rising'] ?? '—', $result['trending'] ?? '—', $result['mainstream'] ?? '—', $result['lead_weeks'] ?? '—', Str::limit((string) $result['failure'], 80)];
        }

        $this->line("Weeks {$from->toDateString()} to {$to->toDateString()}");
        $this->table(['query', 'first rising', 'first trending', 'mainstream', 'lead (weeks)', 'failure'], $rows);

        return self::SUCCESS;
    }
}
