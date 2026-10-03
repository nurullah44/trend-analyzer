<?php

namespace App\Console\Commands;

use App\Read\Ledger;
use Illuminate\Console\Command;

/** The weekly report, as Markdown for people or JSON for agents. */
class TrendsReportCommand extends Command
{
    protected $signature = 'trends:report
        {--week= : The Monday of the week, as YYYY-MM-DD (default: the last finished week)}
        {--json : Print JSON instead of Markdown}';

    protected $description = "The week's Alarms, Source health and the analyzer's gaps";

    public function handle(Ledger $ledger): int
    {
        $report = $ledger->report($this->option('week') ?: null);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line("# Week of {$report['week']}");
        $this->newLine();
        $this->line($report['alarms'] === [] ? 'No Alarms this week.' : '## Alarms');

        foreach ($report['alarms'] as $rank => $alarm) {
            $volumes = collect($alarm['evidence']['volumes'])->map(fn (int $volume, string $source) => "{$source} {$volume}")->join(', ');
            $this->line(sprintf('%d. **%s** — score %s, %d Sources rising (%s); Volume: %s',
                $rank + 1, $alarm['subject'], $alarm['trend_score'], $alarm['corroboration'], implode(', ', $alarm['evidence']['rising']), $volumes));
        }

        $this->newLine();
        $this->line('## Sources');

        foreach (array_filter($report['sources'], fn (array $source) => $source['enabled']) as $source) {
            $this->line(sprintf('- %s: last success %s, %s Items%s', $source['key'], $source['last_success_at'] ?? 'never', $source['last_item_count'] ?? '—', $source['last_error'] ? ", failing: {$source['last_error']}" : ''));
        }

        $this->newLine();
        $this->line('## Gaps');

        foreach ($report['gaps'] ?: ['none'] as $gap) {
            $this->line("- {$gap}");
        }

        return self::SUCCESS;
    }
}
