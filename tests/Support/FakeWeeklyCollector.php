<?php

namespace Tests\Support;

use App\Collection\CollectedDay;
use App\Collection\PublishesWeekly;
use App\Collection\SourceCollector;
use App\Models\Source;
use Carbon\CarbonImmutable;

/** A Source that publishes on Mondays only and remembers the days it was asked for. */
class FakeWeeklyCollector implements PublishesWeekly, SourceCollector
{
    /** @var list<CarbonImmutable> */
    public array $requestedDays = [];

    public function __construct(private readonly string $key) {}

    public function key(): string
    {
        return $this->key;
    }

    public function publishes(CarbonImmutable $day): bool
    {
        return $day->isMonday();
    }

    public function collectForDay(Source $source, CarbonImmutable $day): CollectedDay
    {
        $this->requestedDays[] = $day;

        return new CollectedDay($day, []);
    }
}
