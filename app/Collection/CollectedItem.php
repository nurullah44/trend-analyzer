<?php

namespace App\Collection;

use Carbon\CarbonImmutable;

/**
 * One thing a Source published, as the Source told it to us.
 *
 * `externalId` is the Source's own identifier and is what makes collection
 * idempotent. `excerpt` carries whatever verbatim text the Source offers
 * beyond the title — for Stack Exchange, the question's tags.
 * `measuredQuantity` is the number the Source itself reports for the item,
 * such as a question's score.
 */
final readonly class CollectedItem
{
    public function __construct(
        public string $externalId,
        public string $title,
        public ?string $excerpt = null,
        public ?string $url = null,
        public ?CarbonImmutable $publishedAt = null,
        public int $measuredQuantity = 0,
    ) {}
}
