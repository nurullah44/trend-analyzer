<?php

namespace App\Console\Commands;

use App\Trends\WeeklyRun;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;

/** The weekly run: measure, score and move every tracked Subject for one finished week. */
class TrendsWeeklyCommand extends Command
{
    protected $signature = 'trends:weekly
        {--week= : The Monday of the ISO week to score, as YYYY-MM-DD (default: the last finished week)}';

    protected $description = 'Measure, score and move every tracked Subject for one finished week';

    public function handle(WeeklyRun $run): int
    {
        $week = $this->week();

        if ($week === null) {
            return self::FAILURE;
        }

        $result = $run->run($week);

        $this->info("Week of {$week->toDateString()}: {$result['scored']} Subjects scored, ".count($result['moved']).' moved.');

        foreach ($result['moved'] as $slug => $state) {
            $this->line("  {$slug} → {$state}");
        }

        foreach ($result['failures'] as $source => $error) {
            $this->error("{$source}: {$error}");
        }

        return $result['failures'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function week(): ?CarbonImmutable
    {
        $value = $this->option('week');
        $lastFinished = CarbonImmutable::now('UTC')->startOfWeek()->subWeek();

        if ($value === null || $value === '') {
            return $lastFinished;
        }

        try {
            $week = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (InvalidFormatException) {
            $week = false;
        }

        if ($week === false || $week->format('Y-m-d') !== $value || ! $week->isMonday() || $week > $lastFinished) {
            $this->error("The week must be the Monday of a finished week, as YYYY-MM-DD, got [{$value}].");

            return null;
        }

        return $week;
    }
}
