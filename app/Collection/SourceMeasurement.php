<?php

namespace App\Collection;

/**
 * The measurement contract (ADR-0004): how many items matching one Subject's
 * query a Source published in one week. Measurement Sources answer for any
 * past week, so a newly tracked Subject is backfilled at once.
 */
interface SourceMeasurement
{
    /** The key this Source is registered under in the approved list. */
    public function key(): string;

    /**
     * The Volume for the query in each ISO week starting on the given Mondays
     * (UTC, Y-m-d), or null for a week the Source has nothing it can measure.
     * A Source may answer all the weeks in one request.
     *
     * @param  list<string>  $weeks
     * @return array<string, ?int>
     */
    public function volumes(string $query, array $weeks): array;
}
