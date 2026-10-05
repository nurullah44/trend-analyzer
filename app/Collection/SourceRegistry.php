<?php

namespace App\Collection;

use InvalidArgumentException;

/**
 * The collectors and measurements this app can run, keyed by the Source each one
 * answers for. A Source still waiting for its credentials is not runnable.
 */
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
        return array_filter($this->measurements, fn (SourceMeasurement $measurement) => $this->runnable($measurement));
    }

    public function has(string $key): bool
    {
        return isset($this->collectors[$key]) && $this->runnable($this->collectors[$key]);
    }

    public function for(string $key): SourceCollector
    {
        return $this->has($key) ? $this->collectors[$key] : throw new UnknownSourceException($key);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(array_filter($this->collectors, fn (SourceCollector $collector) => $this->runnable($collector)));
    }

    /** @return list<string> the registered Sources still waiting for their credentials */
    public function unconfigured(): array
    {
        $waiting = array_filter([...$this->collectors, ...$this->measurements], fn (object $source) => ! $this->runnable($source));

        return array_values(array_unique(array_keys($waiting)));
    }

    private function runnable(object $source): bool
    {
        return ! $source instanceof NeedsCredentials || $source->configured();
    }
}
