<?php

namespace Tests\Support;

use App\Collection\SourceMeasurement;
use RuntimeException;

/** A measurement Source that answers from memory: a Volume per week, or a fixed answer for any week. */
final class FakeMeasurement implements SourceMeasurement
{
    /** @var list<string> "query@week" for every question asked */
    public array $asked = [];

    /** @param array<string, ?int>|int|null $volumes week (Y-m-d Monday) => Volume, or one answer for every week */
    public function __construct(
        private readonly string $key,
        private readonly array|int|null $volumes = 0,
        private readonly bool $failing = false,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function volumes(string $query, array $weeks): array
    {
        if ($this->failing) {
            throw new RuntimeException("{$this->key} is down");
        }

        $answers = [];

        foreach ($weeks as $week) {
            $this->asked[] = "{$query}@{$week}";
            $answers[$week] = is_array($this->volumes) ? ($this->volumes[$week] ?? 0) : $this->volumes;
        }

        return $answers;
    }
}
