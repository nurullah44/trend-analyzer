<?php

namespace App\Console\Commands;

use App\Read\OwnerWrites;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** Record the owner's Verdict on an Alarm. Agents run it only when the owner says so. */
class TrendsVerdictCommand extends Command
{
    protected $signature = 'trends:verdict
        {alarm : The Alarm id}
        {verdict : worth_considering or noise}
        {--note= : The owner\'s one-line reason}
        {--magnitude= : small, medium, big or generational}';

    protected $description = "Record the owner's Verdict on an Alarm";

    public function handle(OwnerWrites $writes): int
    {
        if (! ctype_digit((string) $this->argument('alarm'))) {
            $this->error('The Alarm id is a whole number.');

            return self::FAILURE;
        }

        try {
            $alarm = $writes->verdict((int) $this->argument('alarm'), (string) $this->argument('verdict'), $this->option('note') ?: null, $this->option('magnitude') ?: null);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Alarm {$alarm->id}: {$alarm->verdict->value}");

        return self::SUCCESS;
    }
}
