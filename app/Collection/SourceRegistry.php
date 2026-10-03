<?php

namespace App\Collection;

use InvalidArgumentException;

/** The collectors and measurements this app can run, keyed by the Source each one answers for. */
final class SourceRegistry
{
    /**
     * @param  array<string, SourceCollector>  $collectors
     * @param  array<string, SourceMeasurement>  $measurements
     */
    public function __construct(
        private readonly array $collectors = [],
        private readonly array $measurements = [],
    ) {
        foreach ([$collectors, $measurements] as $implementations) {
            foreach ($implementations as $key => $implementation) {
                if ($implementation->key() !== $key) {
                    throw new InvalidArgumentException(
                        "Collector [{$implementation->key()}] is registered under Source [{$key}]."
                    );
                }
            }
        }
    }

    /** @return array<string, SourceMeasurement> */
    public function measurements(): array
    {
        return $this->measurements;
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
