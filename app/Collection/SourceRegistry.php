<?php

namespace App\Collection;

use InvalidArgumentException;

/** The collectors this app can run, keyed by the Source each one answers for. */
final class SourceRegistry
{
    /** @var array<string, SourceCollector> */
    private array $collectors;

    /** @param array<string, SourceCollector> $collectors */
    public function __construct(array $collectors = [])
    {
        foreach ($collectors as $key => $collector) {
            if ($collector->key() !== $key) {
                throw new InvalidArgumentException(
                    "Collector [{$collector->key()}] is registered under Source [{$key}]."
                );
            }
        }

        $this->collectors = $collectors;
    }

    public function has(string $key): bool
    {
        return isset($this->collectors[$key]);
    }

    public function for(string $key): SourceCollector
    {
        return $this->collectors[$key] ?? throw new UnknownSourceException($key);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->collectors);
    }
}
