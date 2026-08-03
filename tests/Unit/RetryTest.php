<?php

declare(strict_types=1);

use NotifyPress\Exception\ConnectionException;
use NotifyPress\Exception\RateLimitException;
use NotifyPress\Exception\ServerException;
use NotifyPress\Exception\TimeoutException;
use NotifyPress\Http\RequestOptions;
use NotifyPress\Tests\Support\MockHttpClient;
use NotifyPress\Tests\Support\RecordingSleeper;

it('retries an idempotent request on a retryable status', function (int $status) {
    $http = MockHttpClient::make()
        ->willRespond($status, ['message' => 'Try later.'])
        ->willRespond(200, ['data' => ['id' => 1]]);
    $sleeper = new RecordingSleeper();

    $result = testTransport($http, $sleeper)->request('GET', '/sites/1');

    expect($http->callCount())->toBe(2)
        ->and($result)->toBe(['data' => ['id' => 1]])
        ->and($sleeper->slept)->toHaveCount(1);
})->with([429, 502, 503, 504]);

it('does not retry a status that is not transient', function (int $status) {
    $http = MockHttpClient::make()->willRespond($status, ['message' => 'Nope.']);

    try {
        testTransport($http, new RecordingSleeper())->request('GET', '/sites/1');
    } catch (Throwable) {
        // The exception itself is covered by the error-mapping tests.
    }

    expect($http->callCount())->toBe(1);
})->with([400, 401, 403, 404, 409, 422]);

it('never retries a mutation, because a send is not idempotent', function () {
    $http = MockHttpClient::make()->willRespond(503, ['message' => 'Unavailable.']);
    $sleeper = new RecordingSleeper();

    expect(fn () => testTransport($http, $sleeper)->request(
        'POST',
        '/notifications/send',
        [],
        ['title' => 'Hi', 'message' => 'There']
    ))->toThrow(ServerException::class);

    expect($http->callCount())->toBe(1)
        ->and($sleeper->slept)->toBeEmpty();
});

it('retries a mutation when the caller opts in', function () {
    $http = MockHttpClient::make()
        ->willRespond(503, ['message' => 'Unavailable.'])
        ->willRespond(200, ['message' => 'Deleted.']);

    $result = testTransport($http, new RecordingSleeper())->request(
        'DELETE',
        '/tags/1',
        [],
        null,
        RequestOptions::make()->retryable()
    );

    expect($http->callCount())->toBe(2)
        ->and($result)->toBe(['message' => 'Deleted.']);
});

it('gives up after maxRetries and throws the last error', function () {
    $http = MockHttpClient::make()
        ->willRespond(503)
        ->willRespond(503)
        ->willRespond(503);
    $sleeper = new RecordingSleeper();

    expect(fn () => testTransport($http, $sleeper, 2)->request('GET', '/sites'))
        ->toThrow(ServerException::class);

    expect($http->callCount())->toBe(3)
        ->and($sleeper->slept)->toHaveCount(2);
});

it('makes exactly one attempt when retries are disabled', function () {
    $http = MockHttpClient::make()->willRespond(503);

    expect(fn () => testTransport($http, new RecordingSleeper(), 0)->request('GET', '/sites'))
        ->toThrow(ServerException::class);

    expect($http->callCount())->toBe(1);
});

it('waits for the number of seconds Retry-After asks for', function () {
    $http = MockHttpClient::make()
        ->willRespond(429, ['message' => 'Slow down.'], ['Retry-After' => '3'])
        ->willRespond(200, ['data' => []]);
    $sleeper = new RecordingSleeper();

    testTransport($http, $sleeper)->request('GET', '/subscribers');

    expect($sleeper->slept)->toBe([3.0]);
});

it('caps the wait at ten seconds however long the server asks for', function () {
    $http = MockHttpClient::make()
        ->willRespond(429, [], ['Retry-After' => '600'])
        ->willRespond(200, ['data' => []]);
    $sleeper = new RecordingSleeper();

    testTransport($http, $sleeper)->request('GET', '/subscribers');

    expect($sleeper->slept)->toBe([10.0]);
});

it('backs off exponentially when the server sends no Retry-After', function () {
    $http = MockHttpClient::make()
        ->willRespond(503)
        ->willRespond(503)
        ->willRespond(200, ['data' => []]);
    $sleeper = new RecordingSleeper();

    testTransport($http, $sleeper, 2)->request('GET', '/sites');

    // Base 0.5s doubling, plus up to 100% jitter, capped at 10s.
    expect($sleeper->slept)->toHaveCount(2)
        ->and($sleeper->slept[0])->toBeGreaterThanOrEqual(0.5)->toBeLessThanOrEqual(1.0)
        ->and($sleeper->slept[1])->toBeGreaterThanOrEqual(1.0)->toBeLessThanOrEqual(2.0);
});

it('retries a connection failure on an idempotent request', function () {
    $http = MockHttpClient::make()
        ->willThrow(new ConnectionException('Could not resolve host.'))
        ->willRespond(200, ['data' => ['id' => 1]]);

    $result = testTransport($http, new RecordingSleeper())->request('GET', '/sites/1');

    expect($http->callCount())->toBe(2)
        ->and($result)->toBe(['data' => ['id' => 1]]);
});

it('rethrows a connection failure once the retries are exhausted', function () {
    $http = MockHttpClient::make()
        ->willThrow(new ConnectionException('Could not resolve host.'))
        ->willThrow(new ConnectionException('Could not resolve host.'));

    expect(fn () => testTransport($http, new RecordingSleeper(), 1)->request('GET', '/sites'))
        ->toThrow(ConnectionException::class, 'Could not resolve host.');

    expect($http->callCount())->toBe(2);
});

it('does not retry a timed-out mutation', function () {
    $http = MockHttpClient::make()->willThrow(new TimeoutException('Timed out.'));

    expect(fn () => testTransport($http, new RecordingSleeper())->request(
        'POST',
        '/notifications/send',
        [],
        ['title' => 'Hi']
    ))->toThrow(TimeoutException::class);

    expect($http->callCount())->toBe(1);
});

it('surfaces a rate limit as a typed exception once retries are spent', function () {
    $http = MockHttpClient::make()
        ->willRespond(429, ['message' => 'Too many.'], ['Retry-After' => '5'])
        ->willRespond(429, ['message' => 'Too many.'], ['Retry-After' => '5']);

    try {
        testTransport($http, new RecordingSleeper(), 1)->request('GET', '/subscribers');
        expect()->fail('Expected a RateLimitException.');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfter)->toBe(5)
            ->and($exception->status)->toBe(429);
    }
});
