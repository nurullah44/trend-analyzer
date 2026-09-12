<?php

namespace App\Collection;

use Carbon\CarbonImmutable;

/** What one Source saw on one day: the UTC day it answers for, and the Items it published. */
final readonly class CollectedDay
{
    /** @param list<CollectedItem> $items */
    public function __construct(
        public CarbonImmutable $day,
        public array $items,
    ) {}

    public function count(): int
    {
        return count($this->items);
    }
}
