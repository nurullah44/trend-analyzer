<?php

namespace App\Console\Commands\Concerns;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/** The `--day` option every daily command shares: a UTC date, yesterday by default. */
trait ReadsDay
{
    /** The day to work on, or null (with the error printed) when the option is not a date. */
    private function day(): ?CarbonImmutable
    {
        $value = $this->option('day');

        if ($value === null || $value === '') {
            return CarbonImmutable::now('UTC')->subDay()->startOfDay();
        }

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (InvalidFormatException) {
            $day = false;
        }

        if ($day === false || $day->format('Y-m-d') !== $value) {
            $this->error("The day must be a UTC date as YYYY-MM-DD, got [{$value}].");

            return null;
        }

        return $day;
    }
}
