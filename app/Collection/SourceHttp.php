<?php

namespace App\Collection;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Throwable;

/** The one way a Source talks HTTP: identified, JSON, retried only on transient failures; callers decide what a failed response means. */
final class SourceHttp
{
    public static function client(Factory $http, string $baseUrl): PendingRequest
    {
        return $http->baseUrl($baseUrl)
            ->acceptJson()
            ->withUserAgent('trend-analyzer/0.1 (github.com/nurullah44/trend-analyzer)')
            ->timeout(30)
            ->retry(2, 500, when: fn (Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)), throw: false);
    }
}
