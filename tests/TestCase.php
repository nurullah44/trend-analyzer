<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // HTTP retries back off between attempts; tests replay fakes, so nothing needs to wait.
        Sleep::fake();

        // The suite never touches the network: an unfaked request fails the test.
        Http::preventStrayRequests();
    }
}
