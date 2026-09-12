<?php

namespace Tests\Support;

/** Reads a recorded Source response, so contract tests never touch the network. */
final class Fixtures
{
    /** @return array<string, mixed> */
    public static function json(string $path): array
    {
        return json_decode(
            file_get_contents(base_path("tests/Fixtures/{$path}")),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
