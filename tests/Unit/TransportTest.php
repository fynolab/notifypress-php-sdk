<?php

declare(strict_types=1);

use NotifyPress\Config;
use NotifyPress\Http\Multipart\MultipartBody;
use NotifyPress\Http\RequestOptions;
use NotifyPress\Http\RetryPolicy;
use NotifyPress\Http\Transport;
use NotifyPress\Support\Json;
use NotifyPress\Tests\Support\MockHttpClient;
use NotifyPress\Tests\Support\RecordingSleeper;
use NotifyPress\Version;

function transportFor(MockHttpClient $http, string $token = '1|test-secret'): Transport
{
    return testTransport($http, new RecordingSleeper(), 0, $token);
}

it('joins the base URL and path without doubling slashes', function (string $baseUrl, string $path) {
    $http = MockHttpClient::make()->willRespond();

    $transport = new Transport(
        Config::make('1|secret')->withBaseUrl($baseUrl),
        $http,
        new RetryPolicy(0),
        new RecordingSleeper()
    );

    $transport->request('GET', $path);

    expect($http->lastRequest()->uri)->toBe('https://api.test/api/v1/sites');
})->with([
    ['https://api.test/api/v1', '/sites'],
    ['https://api.test/api/v1/', '/sites'],
    ['https://api.test/api/v1', 'sites'],
    ['https://api.test/api/v1///', 'sites'],
]);

it('sends the API token as a Bearer header', function () {
    $http = MockHttpClient::make()->willRespond();

    transportFor($http)->request('GET', '/sites');

    expect($http->lastRequest()->headers['Authorization'])->toBe('Bearer 1|test-secret');
});

it('omits the Authorization header when no token is configured', function () {
    $http = MockHttpClient::make()->willRespond();

    transportFor($http, '')->request('GET', '/public/sites/npk_x/notifications/recent');

    expect($http->lastRequest()->headers)->not->toHaveKey('Authorization');
});

it('always asks for JSON and identifies itself', function () {
    $http = MockHttpClient::make()->willRespond();

    transportFor($http)->request('GET', '/sites');

    expect($http->lastRequest()->headers['Accept'])->toBe('application/json')
        ->and($http->lastRequest()->headers['User-Agent'])->toBe(Version::userAgent())
        ->and(Version::userAgent())->toStartWith('notifypress-php/');
});

it('JSON-encodes an array body and sets the content type', function () {
    $http = MockHttpClient::make()->willRespond();

    transportFor($http)->request('POST', '/tags', [], ['name' => 'News']);

    expect($http->lastRequest()->headers['Content-Type'])->toBe('application/json')
        ->and($http->lastRequest()->body)->toBe(Json::encode(['name' => 'News']));
});

it('sends no content type when there is no body', function () {
    $http = MockHttpClient::make()->willRespond();

    transportFor($http)->request('POST', '/notifications/1/cancel');

    expect($http->lastRequest()->headers)->not->toHaveKey('Content-Type')
        ->and($http->lastRequest()->body)->toBeNull();
});

it('uses the multipart content type and boundary for uploads', function () {
    $http = MockHttpClient::make()->willRespond();
    $body = MultipartBody::make('TESTBOUNDARY')->field('title', 'Hello');

    transportFor($http)->request('POST', '/notifications', [], $body);

    expect($http->lastRequest()->headers['Content-Type'])
        ->toBe('multipart/form-data; boundary=TESTBOUNDARY')
        ->and($http->lastRequest()->body)->toContain('--TESTBOUNDARY');
});

it('appends query parameters and skips empty ones', function () {
    $http = MockHttpClient::make()->willRespond();

    transportFor($http)->request('GET', '/notifications', [
        'limit' => 50,
        'status' => 'sent',
        'cursor' => null,
        'order' => '',
    ]);

    expect(queryOf($http->lastRequest()->uri))->toBe(['limit' => '50', 'status' => 'sent']);
});

it('decodes a JSON response body', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => ['id' => 7]]);

    expect(transportFor($http)->request('GET', '/sites/7'))->toBe(['data' => ['id' => 7]]);
});

it('decodes an empty body to an empty array rather than null', function (int $status, string $body) {
    $http = MockHttpClient::make()->willRespondRaw($status, $body);

    expect(transportFor($http)->request('DELETE', '/tags/1'))->toBe([]);
})->with([
    'no content' => [204, ''],
    'blank body' => [200, '   '],
]);

it('merges per-call headers over the defaults', function () {
    $http = MockHttpClient::make()->willRespond();

    transportFor($http)->request(
        'GET',
        '/sites',
        [],
        null,
        RequestOptions::make()->withHeader('X-Trace', 'abc')
    );

    expect($http->lastRequest()->headers['X-Trace'])->toBe('abc')
        ->and($http->lastRequest()->headers['Accept'])->toBe('application/json');
});

it('applies a per-call timeout override', function () {
    $http = MockHttpClient::make()->willRespond();

    transportFor($http)->request('GET', '/sites', [], null, RequestOptions::make()->timeout(2.5));

    expect($http->lastRequest()->timeout)->toBe(2.5);
});

it('returns the untouched response for binary payloads', function () {
    $http = MockHttpClient::make()->willRespondRaw(200, '%PDF-1.4 fake', ['Content-Type' => 'application/pdf']);

    $response = transportFor($http)->requestRaw('GET', '/reports/download');

    expect($response->body)->toBe('%PDF-1.4 fake')
        ->and($response->header('content-type'))->toBe('application/pdf')
        ->and($response->isJson())->toBeFalse();
});

it('writes a raw response body to disk', function () {
    $http = MockHttpClient::make()->willRespondRaw(200, '%PDF-1.4 fake');
    $path = sys_get_temp_dir() . '/notifypress-sdk-test.pdf';

    transportFor($http)->requestRaw('GET', '/reports/download')->saveTo($path);

    expect(file_get_contents($path))->toBe('%PDF-1.4 fake');

    unlink($path);
});

it('uppercases the method so callers may pass any case', function () {
    $http = MockHttpClient::make()->willRespond();

    transportFor($http)->request('post', '/tags', [], ['name' => 'News']);

    expect($http->lastRequest()->method)->toBe('POST');
});
