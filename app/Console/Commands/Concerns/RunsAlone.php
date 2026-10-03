<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\Cache;

/** A scheduled run never overlaps another copy of itself: a doubled trigger simply exits. */
trait RunsAlone
{
    /** @param callable(): int $run */
    private function alone(callable $run): int
    {
        $lock = Cache::lock('run:'.$this->getName(), 6 * 3600);

        if (! $lock->get()) {
            $this->warn("{$this->getName()} is already running.");

            return self::SUCCESS;
        }

        try {
            return $run();
        } finally {
            $lock->release();
        }
    }
}
