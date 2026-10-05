<?php

namespace App\Collection;

use Carbon\CarbonImmutable;

/**
 * A discovery Source that publishes once a week rather than every day: it is
 * only collected for the days it publishes on, as soon as it has published,
 * and a quiet week in between is not a gap.
 */
interface PublishesWeekly
{
    /** Whether the Source has, by now, published anything for the given UTC day. */
    public function publishes(CarbonImmutable $day): bool;
}
