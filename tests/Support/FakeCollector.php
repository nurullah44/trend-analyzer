<?php

namespace Tests\Support;

use App\Collection\CollectedDay;
use App\Collection\CollectedItem;
use App\Collection\SourceCollector;
use App\Models\Source;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/** A Source that answers from memory, so the collection pipeline can be driven without the network. */
final class FakeCollector implements SourceCollector
{
    /** @var list<CarbonImmutable> */
    public array $requestedDays = [];

    /** @var list<Source> */
    public array $requestedSources = [];

    /** @param list<CollectedItem> $items */
    public function __construct(
        private readonly string $key,
        private readonly array $items = [],
        private readonly ?Throwable $failure = null,
    ) {}

    public static function returning(string $key, CollectedItem ...$items): self
    {
        return new self($key, array_values($items));
    }

    public static function failing(string $key, string $message = 'the Source is down'): self
    {
        return new self($key, failure: new RuntimeException($message));
    }

    public function key(): string
    {
        return $this->key;
    }

    public function collectForDay(Source $source, CarbonImmutable $day): CollectedDay
    {
        $this->requestedDays[] = $day;
        $this->requestedSources[] = $source;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new CollectedDay($day, $this->items);
    }
}
