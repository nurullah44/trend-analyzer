<?php

namespace App\Console\Commands;

use App\Read\Ledger;
use Illuminate\Console\Command;

/** The read layer as JSON, for agents (pi) and for anyone who wants the raw answer. */
class TrendsShowCommand extends Command
{
    protected $signature = 'trends:show
        {what : report, alarms, subjects, subject, sources or gaps}
        {slug? : The Subject, for "subject"}
        {--week= : The Monday of a week, for "report" and "alarms"}
        {--state= : Narrow "subjects" to one state}
        {--label= : Narrow "subjects" to one Label}';

    protected $description = 'Read what the analyzer knows, as JSON';

    public function handle(Ledger $ledger): int
    {
        $week = $this->option('week') ?: null;

        $answer = match ($this->argument('what')) {
            'report' => $ledger->report($week),
            'alarms' => $ledger->alarms($week),
            'subjects' => $ledger->subjects($this->option('state') ?: null, $this->option('label') ?: null),
            'subject' => $ledger->subject((string) $this->argument('slug')) ?? ['error' => "No Subject has slug [{$this->argument('slug')}]."],
            'sources' => $ledger->sources(),
            'gaps' => $ledger->gaps(),
            default => ['error' => 'Ask for one of: report, alarms, subjects, subject, sources, gaps.'],
        };

        $this->line(json_encode($answer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return isset($answer['error']) ? self::FAILURE : self::SUCCESS;
    }
}
