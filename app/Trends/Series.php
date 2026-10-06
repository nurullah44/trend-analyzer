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
     * Measure the missing weeks up to and including the given one, on every
     * measurement Source or only the ones named.
     *
     * @param  list<string>|null  $only
     * @return array<string, string> Source key => error, for the Sources that failed
     */
    public function measure(Subject $subject, CarbonImmutable $week, ?array $only = null): array
    {
        $failures = [];

        foreach ($this->measurementSources($only) as $source) {
            $weeks = $this->horizon($week, $source->key);
            $known = $subject->weeks()->where('source_id', $source->id)->where('query', $subject->query)->pluck('week')->all();

            try {
                $missing = array_values(array_diff($weeks, $known));
                $volumes = $missing === [] ? [] : $this->sources->measurements()[$source->key]->volumes($subject->query, $missing);

                // A week left out of the answer is one the Source no longer keeps: unknown, so nothing is stored.
                foreach (array_filter($missing, fn (string $monday) => array_key_exists($monday, $volumes)) as $monday) {
                    SubjectWeek::create([
                        'subject_id' => $subject->id,
                        'source_id' => $source->id,
                        'query' => $subject->query,
                        'week' => $monday,
                        'volume' => $volumes[$monday] ?? null,
                    ]);
                }

                $source->update(['last_success_at' => now(), 'last_error' => null]);

                // Without the scored week itself the week is incomplete, so it can move nothing.
                if (in_array($week->toDateString(), $missing, true) && ! array_key_exists($week->toDateString(), $volumes)) {
                    $failures[$source->key] = "{$source->key} no longer keeps the week of {$week->toDateString()}";
                }
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

    /** @return list<string> the weeks a score needs from the Source: its lookback and the week itself */
    private function horizon(CarbonImmutable $week, string $source): array
    {
        return array_map(
            fn (int $back) => $week->subWeeks($back)->toDateString(),
            range(Scorer::lookback($source), 0),
        );
    }

    /**
     * @param  list<string>|null  $only
     * @return iterable<Source>
     */
    private function measurementSources(?array $only): iterable
    {
        return Source::query()
            ->where('enabled', true)
            ->whereIn('key', array_keys($this->sources->measurements()))
            ->when($only !== null, fn ($query) => $query->whereIn('key', $only))
            ->orderBy('key')
            ->get();
    }
}
