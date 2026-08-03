<?php

declare(strict_types=1);

use NotifyPress\Exception\ApiException;
use NotifyPress\Exception\AuthenticationException;
use NotifyPress\Exception\ErrorFactory;
use NotifyPress\Exception\ForbiddenException;
use NotifyPress\Exception\NotFoundException;
use NotifyPress\Exception\PaidPlanException;
use NotifyPress\Exception\RateLimitException;
use NotifyPress\Exception\ServerException;
use NotifyPress\Exception\ValidationException;
use NotifyPress\Http\Response;
use NotifyPress\Support\Json;
use NotifyPress\Tests\Support\MockHttpClient;
use NotifyPress\Tests\Support\RecordingSleeper;

/**
 * @param array<string, mixed>  $body
 * @param array<string, string> $headers
 */
function errorResponse(int $status, array $body = [], array $headers = []): Response
{
    return new Response(
        $status,
        array_merge(['Content-Type' => 'application/json'], $headers),
        Json::encode($body)
    );
}

it('maps each status to the most specific exception', function (int $status, array $body, string $expected) {
    /** @var array<string, mixed> $body */
    expect(ErrorFactory::create(errorResponse($status, $body))::class)->toBe($expected);
})->with([
    'validation' => [422, ['message' => 'Invalid.', 'errors' => ['title' => ['Required.']]], ValidationException::class],
    'unauthenticated' => [401, ['message' => 'Unauthenticated.'], AuthenticationException::class],
    'rejected token' => [401, ['error' => 'Invalid token'], AuthenticationException::class],
    'paid gate' => [403, ['message' => 'Upgrade.', 'upgrade_url' => 'https://x/billing'], PaidPlanException::class],
    'token verification' => [403, ['error' => 'Token mismatch'], AuthenticationException::class],
    'plain forbidden' => [403, ['message' => 'Forbidden.'], ForbiddenException::class],
    'missing' => [404, ['message' => 'Not found.'], NotFoundException::class],
    'rate limited' => [429, ['message' => 'Too many.'], RateLimitException::class],
    'server error' => [500, ['message' => 'Boom.'], ServerException::class],
    'gateway' => [502, [], ServerException::class],
    'anything else' => [418, ['message' => 'Teapot.'], ApiException::class],
]);

it('reads the message from either body shape, falling back to the status', function (array $body, string $expected) {
    /** @var array<string, mixed> $body */
    expect(ErrorFactory::create(errorResponse(400, $body))->getMessage())->toBe($expected);
})->with([
    'message key' => [['message' => 'Something broke.'], 'Something broke.'],
    'error key' => [['error' => 'Bad credential.'], 'Bad credential.'],
    'message wins over error' => [['message' => 'First.', 'error' => 'Second.'], 'First.'],
    'neither' => [[], 'Request failed with status 400'],
    'empty message' => [['message' => ''], 'Request failed with status 400'],
]);

it('exposes field errors from a 422', function () {
    $exception = ErrorFactory::create(errorResponse(422, [
        'message' => 'The given data was invalid.',
        'errors' => [
            'title' => ['The title field is required.', 'The title may not be greater than 50 characters.'],
            'message' => ['The message field is required.'],
        ],
    ]));

    expect($exception)->toBeInstanceOf(ValidationException::class);
    assert($exception instanceof ValidationException);

    expect($exception->errors)->toBe([
        'title' => ['The title field is required.', 'The title may not be greater than 50 characters.'],
        'message' => ['The message field is required.'],
    ])
        ->and($exception->fields())->toBe(['title', 'message'])
        ->and($exception->first('title'))->toBe('The title field is required.')
        ->and($exception->first('icon_url'))->toBeNull();
});

it('carries the upgrade URL on a paid-plan gate', function () {
    $exception = ErrorFactory::create(errorResponse(403, [
        'message' => 'This feature requires a paid plan.',
        'upgrade_url' => 'https://app.notifypress.com/billing',
    ]));

    expect($exception)->toBeInstanceOf(PaidPlanException::class);
    assert($exception instanceof PaidPlanException);

    expect($exception->upgradeUrl)->toBe('https://app.notifypress.com/billing');
});

it('prefers the paid-plan gate over the rejected-token shape on a 403', function () {
    // A body carrying both must not be reported as an auth failure — the
    // account is authenticated, it just is not entitled.
    $exception = ErrorFactory::create(errorResponse(403, [
        'error' => 'Forbidden',
        'upgrade_url' => 'https://app.notifypress.com/billing',
    ]));

    expect($exception)->toBeInstanceOf(PaidPlanException::class);
});

it('reads Retry-After as seconds or as an HTTP date', function () {
    $seconds = ErrorFactory::create(errorResponse(429, [], ['Retry-After' => '42']));
    $httpDate = ErrorFactory::create(
        errorResponse(429, [], ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 30)])
    );

    expect($seconds)->toBeInstanceOf(RateLimitException::class);
    assert($seconds instanceof RateLimitException);
    assert($httpDate instanceof RateLimitException);

    expect($seconds->retryAfter)->toBe(42)
        ->and($seconds->retryAt())->not->toBeNull()
        ->and($httpDate->retryAfter)->toBeGreaterThanOrEqual(28)
        ->and($httpDate->retryAfter)->toBeLessThanOrEqual(30);
});

it('reports no retry delay when the header is absent or unparseable', function (?string $header) {
    $headers = $header === null ? [] : ['Retry-After' => $header];

    expect(ErrorFactory::retryAfter(errorResponse(429, [], $headers)))->toBeNull();
})->with([
    'absent' => [null],
    'blank' => ['  '],
    'nonsense' => ['soon'],
]);

it('survives an HTML error body from a proxy', function () {
    $response = new Response(502, ['Content-Type' => 'text/html'], '<html>Bad Gateway</html>');
    $exception = ErrorFactory::create($response);

    expect($exception)->toBeInstanceOf(ServerException::class)
        ->and($exception->getMessage())->toBe('Request failed with status 502')
        ->and($exception->status)->toBe(502);
});

it('keeps the status and decoded body on the exception', function () {
    $exception = ErrorFactory::create(errorResponse(404, ['message' => 'Site not found.']));

    expect($exception->status)->toBe(404)
        ->and($exception->body)->toBe(['message' => 'Site not found.']);
});

it('throws the mapped exception out of the transport', function () {
    $http = MockHttpClient::make()->willRespond(422, [
        'message' => 'The given data was invalid.',
        'errors' => ['title' => ['The title field is required.']],
    ]);

    testTransport($http, new RecordingSleeper(), 0)->request('POST', '/notifications/send', [], ['message' => 'x']);
})->throws(ValidationException::class, 'The given data was invalid.');
