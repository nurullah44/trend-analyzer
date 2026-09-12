<?php

namespace App\Collection;

use Carbon\CarbonImmutable;

/** What happened when one Source was collected for one day. */
final readonly class CollectionOutcome
{
    private function __construct(
        public string $sourceKey,
        public CarbonImmutable $day,
        public int $itemCount,
        public ?string $error,
    ) {}

    public static function success(string $sourceKey, CarbonImmutable $day, int $itemCount): self
    {
        return new self($sourceKey, $day, $itemCount, null);
    }

    public static function failure(string $sourceKey, CarbonImmutable $day, string $error): self
    {
        return new self($sourceKey, $day, 0, $error);
    }

    public function ok(): bool
    {
        return $this->error === null;
    }
}
