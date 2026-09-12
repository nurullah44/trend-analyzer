<?php

namespace App\Collection;

use App\Models\Source;
use Carbon\CarbonImmutable;

/**
 * The one contract every Source implements.
 *
 * A collector knows its own identity and fetches everything its Source
 * published on a given UTC day. It only fetches: the CollectionRunner persists
 * what comes back and records the Source's health. No collector reaches for
 * another provider, and nothing outside a collector talks to one.
 */
interface SourceCollector
{
    /** The key this Source is registered under in the approved list. */
    public function key(): string;

    /** Everything the Source published on the given day, answered for that UTC day. */
    public function collectForDay(Source $source, CarbonImmutable $day): CollectedDay;
}
