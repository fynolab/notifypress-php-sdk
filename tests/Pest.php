<?php

declare(strict_types=1);

use NotifyPress\Config;
use NotifyPress\Http\RetryPolicy;
use NotifyPress\Http\Transport;
use NotifyPress\NotifyPress;
use NotifyPress\Tests\Support\MockHttpClient;
use NotifyPress\Tests\Support\RecordingSleeper;

/**
 * A client wired to a mock HTTP client. Retries are off by default so a test
 * asserting an error does not silently make three requests.
 */
function testClient(MockHttpClient $http, int $maxRetries = 0): NotifyPress
{
    return new NotifyPress(
        Config::make('1|test-secret')
            ->withBaseUrl('https://api.test/api/v1')
            ->withHttpClient($http)
            ->withMaxRetries($maxRetries)
    );
}

/**
 * A transport with an injected sleeper, for asserting backoff without waiting.
 */
function testTransport(
    MockHttpClient $http,
    RecordingSleeper $sleeper,
    int $maxRetries = 2,
    string $token = '1|test-secret',
): Transport {
    return new Transport(
        Config::make($token)->withBaseUrl('https://api.test/api/v1'),
        $http,
        new RetryPolicy($maxRetries),
        $sleeper
    );
}

/**
 * The query string of a recorded request URI, parsed.
 *
 * @return array<string, string>
 */
function queryOf(string $uri): array
{
    $query = parse_url($uri, PHP_URL_QUERY);

    if (! is_string($query)) {
        return [];
    }

    parse_str($query, $parsed);

    /** @var array<string, string> $parsed */
    return $parsed;
}
