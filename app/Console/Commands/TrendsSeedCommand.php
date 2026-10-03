<?php

namespace App\Console\Commands;

use App\Read\OwnerWrites;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** Add a Seed Subject, or promote one waiting in Backlog. Agents run it only when the owner says so. */
class TrendsSeedCommand extends Command
{
    protected $signature = 'trends:seed
        {name : The Subject, in five words or fewer}
        {--query= : What to search the Sources for (default: the name)}
        {--label=* : Labels to attach}';

    protected $description = 'Add a Seed Subject, or promote one waiting in Backlog';

    public function handle(OwnerWrites $writes): int
    {
        try {
            $subject = $writes->seed((string) $this->argument('name'), $this->option('query') ?: null, $this->option('label'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$subject->slug}: {$subject->state->value}, query \"{$subject->query}\"");

        return self::SUCCESS;
    }
}
