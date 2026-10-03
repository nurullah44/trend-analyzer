<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ReadsDay;
use App\Subjects\Intake;
use Illuminate\Console\Command;

/** Turns a collected day's Items into Subjects through the Classifier. */
class TrendsDiscoverCommand extends Command
{
    use ReadsDay;

    protected $signature = 'trends:discover
        {--day= : The UTC day whose Items to read, as YYYY-MM-DD (default: yesterday)}';

    protected $description = "Propose Candidates from a day's Items and route them through the Classifier";

    public function handle(Intake $intake): int
    {
        $day = $this->day();

        if ($day === null) {
            return self::FAILURE;
        }

        $outcomes = $intake->discover($day);

        $this->table(array_keys($outcomes), [array_values($outcomes)]);

        return self::SUCCESS;
    }
}
