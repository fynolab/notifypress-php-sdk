<?php

declare(strict_types=1);

use NotifyPress\Config;
use NotifyPress\Exception\ConfigurationException;
use NotifyPress\Http\Client\ClientFactory;
use NotifyPress\Http\Client\CurlClient;
use NotifyPress\Http\Client\Psr17FactoryLocator;
use NotifyPress\Http\Client\Psr18Client;
use NotifyPress\Http\Client\StreamClient;
use NotifyPress\Http\Client\WordPressClient;
use NotifyPress\NotifyPress;
use NotifyPress\Tests\Support\MockHttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

it('picks the Guzzle-backed PSR-18 client when Guzzle is installed', function () {
    // This is the Laravel case: the application's own HTTP stack gets reused
    // rather than the SDK opening its own sockets.
    expect(class_exists(GuzzleHttp\Client::class))->toBeTrue()
        ->and(WordPressClient::isSupported())->toBeFalse()
        ->and(ClientFactory::detect())->toBeInstanceOf(Psr18Client::class);
});

it('confirms Guzzle satisfies PSR-18 natively, so no adapter is needed', function () {
    expect(new GuzzleHttp\Client())->toBeInstanceOf(Psr\Http\Client\ClientInterface::class);
});

it('finds a PSR-17 factory implementing both halves of the standard', function () {
    $factory = Psr17FactoryLocator::find();

    expect($factory)->toBeInstanceOf(RequestFactoryInterface::class)
        ->and($factory)->toBeInstanceOf(StreamFactoryInterface::class)
        ->and(Psr17FactoryLocator::isAvailable())->toBeTrue();
});

it('accepts an extra PSR-17 implementation the SDK does not know about', function () {
    Psr17FactoryLocator::register('Some\\Unknown\\Psr17Factory');

    // Registering a class that is not installed must be harmless — detection
    // simply moves on to the next candidate.
    expect(Psr17FactoryLocator::find())->not->toBeNull();
});

it('reports a usable dependency-free fallback on this runtime', function () {
    expect(CurlClient::isSupported() || StreamClient::isSupported())->toBeTrue();
});

it('uses an explicitly injected client over anything it would detect', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => ['id' => 1]]);

    testClient($http)->sites->current();

    expect($http->callCount())->toBe(1);
});

it('refuses to build a client without an API token', function () {
    expect(fn () => new NotifyPress(''))
        ->toThrow(ConfigurationException::class)
        ->and(fn () => new NotifyPress(Config::make()))
        ->toThrow(ConfigurationException::class);
});

it('carries the configuration into a derived client', function () {
    $http = MockHttpClient::make();
    $client = testClient($http);
    $staging = $client->withBaseUrl('https://staging.test/api/v1');

    expect($client->config()->baseUrl)->toBe('https://api.test/api/v1')
        ->and($staging->config()->baseUrl)->toBe('https://staging.test/api/v1')
        ->and($staging->config()->apiToken)->toBe('1|test-secret')
        ->and($staging->config()->httpClient)->toBe($http);
});

it('switches sites by swapping the token, keeping everything else', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => ['id' => 2]]);

    $other = testClient($http)->withApiToken('2|other-secret');
    $other->sites->current();

    expect($http->lastRequest()->headers['Authorization'])->toBe('Bearer 2|other-secret')
        ->and($http->lastRequest()->uri)->toStartWith('https://api.test/api/v1');
});

it('exposes the transport for endpoints the SDK does not model yet', function () {
    $http = MockHttpClient::make()->willRespond(200, ['anything' => true]);

    $result = testClient($http)->transport()->request('GET', '/some/new/endpoint');

    expect($result)->toBe(['anything' => true])
        ->and($http->lastRequest()->headers['Authorization'])->toBe('Bearer 1|test-secret');
});
