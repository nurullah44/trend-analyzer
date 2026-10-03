<?php

namespace App\Console\Commands;

use App\Read\OwnerWrites;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** Attach a Label to a Subject, or take it off. Agents run it only when the owner says so. */
class TrendsLabelCommand extends Command
{
    protected $signature = 'trends:label
        {subject : The Subject slug}
        {label : The Label name}
        {--remove : Take the Label off instead}';

    protected $description = 'Attach a Label to a Subject, or take it off';

    public function handle(OwnerWrites $writes): int
    {
        try {
            $subject = $writes->label((string) $this->argument('subject'), (string) $this->argument('label'), (bool) $this->option('remove'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$subject->slug}: ".($subject->labels->pluck('name')->join(', ') ?: 'no Labels'));

        return self::SUCCESS;
    }
}
