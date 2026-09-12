<?php

namespace App\Collection;

use RuntimeException;

/** A Source has no collector registered, so it cannot be collected. */
final class UnknownSourceException extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct("No collector is registered for Source [{$key}].");
    }
}
