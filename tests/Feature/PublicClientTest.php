<?php

declare(strict_types=1);

use NotifyPress\Config;
use NotifyPress\Exception\ConfigurationException;
use NotifyPress\NotifyPressPublic;
use NotifyPress\Payload\PublicSubscribe;
use NotifyPress\Tests\Support\MockHttpClient;

function publicClient(MockHttpClient $http, string $siteUrl = 'https://example.com'): NotifyPressPublic
{
    return new NotifyPressPublic(
        'npk_abc123',
        $siteUrl,
        Config::make('should-be-ignored')
            ->withBaseUrl('https://api.test/api/v1')
            ->withHttpClient($http)
            ->withMaxRetries(0)
    );
}

it('subscribes a visitor', function () {
    $http = MockHttpClient::make()->willRespond(201, [
        'data' => ['id' => 5, 'site_id' => 3, 'token' => 'fcm-abc', 'active' => true, 'premium_user' => false],
    ]);

    $subscriber = publicClient($http)->subscribe(
        PublicSubscribe::make('fcm-abc')->browser('Chrome')->device('Desktop')->country('CA')
    );

    expect($http->lastRequest()->uri)
        ->toBe('https://api.test/api/v1/public/sites/npk_abc123/subscribers')
        ->and($http->bodyAt(0))->toBe([
            'active' => true,
            'token' => 'fcm-abc',
            'browser' => 'Chrome',
            'device' => 'Desktop',
            'country' => 'CA',
        ])
        ->and($subscriber->id)->toBe(5)
        ->and($subscriber->active)->toBeTrue();
});

it('accepts a bare FCM token as shorthand', function () {
    $http = MockHttpClient::make()->willRespond(201, ['data' => ['id' => 5, 'token' => 'fcm-abc']]);

    publicClient($http)->subscribe('fcm-abc');

    expect($http->bodyAt(0))->toBe(['active' => true, 'token' => 'fcm-abc']);
});

it('sends an Origin the server can match against the registered site', function () {
    // Without this header every public call 403s, because the endpoints are
    // designed for browsers, which set it themselves.
    $http = MockHttpClient::make()->willRespond(201, ['data' => ['id' => 5, 'token' => 'fcm-abc']]);

    publicClient($http, 'https://example.com/')->subscribe('fcm-abc');

    expect($http->lastRequest()->headers['Origin'])->toBe('https://example.com')
        ->and($http->lastRequest()->headers['Referer'])->toBe('https://example.com/');
});

it('never sends an API token to the public endpoints', function () {
    $http = MockHttpClient::make()->willRespond(201, ['data' => ['id' => 5, 'token' => 'fcm-abc']]);

    publicClient($http)->subscribe('fcm-abc');

    expect($http->lastRequest()->headers)->not->toHaveKey('Authorization');
});

it('unsubscribes without deleting the subscriber', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => ['id' => 5, 'token' => 'fcm-abc', 'active' => false],
    ]);

    $subscriber = publicClient($http)->unsubscribe('fcm-abc');

    expect($http->bodyAt(0))->toBe(['token' => 'fcm-abc', 'active' => false])
        ->and($subscriber->active)->toBeFalse();
});

it('erases a subscriber', function () {
    $http = MockHttpClient::make()->willRespond(200, ['deleted' => true]);

    expect(publicClient($http)->erase('fcm-abc'))->toBeTrue()
        ->and($http->lastRequest()->uri)
        ->toBe('https://api.test/api/v1/public/sites/npk_abc123/subscribers/erase');
});

it('reports an erase that deleted nothing', function () {
    $http = MockHttpClient::make()->willRespond(200, ['deleted' => false]);

    expect(publicClient($http)->erase('fcm-abc'))->toBeFalse();
});

it('lists the recent notifications a visitor may have missed', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [
            ['id' => 1, 'title' => 'Latest', 'target_url' => 'https://example.com/post'],
            ['id' => 2, 'title' => 'Older', 'target_url' => null],
        ],
    ]);

    $recent = publicClient($http)->recentNotifications();

    expect($recent)->toHaveCount(2)
        ->and($recent[0]->title)->toBe('Latest')
        ->and($recent[1]->targetUrl)->toBeNull()
        ->and($http->lastRequest()->uri)
        ->toBe('https://api.test/api/v1/public/sites/npk_abc123/notifications/recent');
});

it('tracks clicks and views without authentication', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['message' => 'ok', 'clicks' => 1])
        ->willRespond(200, ['message' => 'ok', 'views' => 1]);

    $client = publicClient($http);
    $client->trackClick(9);
    $client->trackView(9, ['device' => 'Mobile']);

    expect($http->requestAt(0)->uri)->toBe('https://api.test/api/v1/notifications/9/click')
        ->and($http->bodyAt(1))->toBe(['viewed' => true, 'device' => 'Mobile']);
});

it('escapes the public key in the path', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => []]);

    (new NotifyPressPublic(
        'npk_a/b',
        'https://example.com',
        Config::make()->withBaseUrl('https://api.test/api/v1')->withHttpClient($http)
    ))->recentNotifications();

    expect($http->lastRequest()->uri)
        ->toBe('https://api.test/api/v1/public/sites/npk_a%2Fb/notifications/recent');
});

it('refuses to build without the credentials the endpoints need', function (string $publicKey, string $siteUrl) {
    expect(fn () => new NotifyPressPublic($publicKey, $siteUrl))->toThrow(ConfigurationException::class);
})->with([
    'no public key' => ['', 'https://example.com'],
    'no site url' => ['npk_abc', ''],
]);
