<?php

namespace App\Trends;

use App\Collection\SourceRegistry;
use App\Models\Source;
use App\Models\Subject;
use App\Models\SubjectWeek;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * A Subject's weekly series (ADR-0004): every measurement Source is asked for
 * each week it has not answered yet, back to the baseline horizon, so a newly
 * tracked Subject is backfilled and a missed run heals on the next one.
 */
final class Series
{
    public function __construct(private readonly SourceRegistry $sources) {}

    /**
     * Measure the missing weeks up to and including the given one.
     *
     * @return array<string, string> Source key => error, for the Sources that failed
     */
    public function measure(Subject $subject, CarbonImmutable $week): array
    {
        $failures = [];
        $weeks = $this->horizon($week);

        foreach ($this->measurementSources() as $source) {
            $known = $subject->weeks()->where('source_id', $source->id)->where('query', $subject->query)->pluck('week')->all();

            try {
                $missing = array_values(array_diff($weeks, $known));
                $volumes = $missing === [] ? [] : $this->sources->measurements()[$source->key]->volumes($subject->query, $missing);

                foreach ($missing as $week) {
                    SubjectWeek::create([
                        'subject_id' => $subject->id,
                        'source_id' => $source->id,
                        'query' => $subject->query,
                        'week' => $week,
                        'volume' => $volumes[$week] ?? null,
                    ]);
                }

                $source->update(['last_success_at' => now(), 'last_error' => null]);
            } catch (Throwable $e) {
                $failures[$source->key] = $e->getMessage();
                $source->update(['last_error' => Str::limit($e->getMessage(), 500)]);
            }
        }

        return $failures;
    }

    /**
     * The stored series for the Subject's query, ready for the Scorer.
     *
     * @return array<string, array<string, ?int>> Source key => week => Volume
     */
    public function of(Subject $subject): array
    {
        $series = [];

        foreach ($subject->weeks()->where('query', $subject->query)->with('source')->orderBy('week')->get() as $row) {
            $series[$row->source->key][$row->week] = $row->volume;
        }

        return $series;
    }

    /** @return list<string> the weeks a score needs: the baseline and the week itself */
    private function horizon(CarbonImmutable $week): array
    {
        return array_map(
            fn (int $back) => $week->subWeeks($back)->toDateString(),
            range(config('trend.scoring.baseline_weeks'), 0),
        );
    }

    /** @return iterable<Source> */
    private function measurementSources(): iterable
    {
        return Source::query()
            ->where('enabled', true)
            ->whereIn('key', array_keys($this->sources->measurements()))
            ->orderBy('key')
            ->get();
    }
}
