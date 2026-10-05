<?php

namespace App\Collection;

/**
 * A Source that cannot be asked anything without the owner's credentials. Until
 * they are all set it is left out of every run, and the status and the report
 * say why, so a missing key degrades the analyzer instead of failing it.
 */
interface NeedsCredentials
{
    public function configured(): bool;
}
