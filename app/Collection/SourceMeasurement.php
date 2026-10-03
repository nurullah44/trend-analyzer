<?php

namespace App\Collection;

use Carbon\CarbonImmutable;

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
     * The Volume for the query in the ISO week starting on the given Monday (UTC),
     * or null when the Source has nothing it can measure for this query.
     */
    public function volume(string $query, CarbonImmutable $week): ?int;
}
