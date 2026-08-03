<?php

declare(strict_types=1);

use NotifyPress\Enum\PromptStyle;
use NotifyPress\Exception\NotFoundException;
use NotifyPress\Payload\SiteUpdate;
use NotifyPress\Tests\Support\MockHttpClient;

it('retrieves the site the token belongs to', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [
            'id' => 3,
            'site_name' => 'Example',
            'site_url' => 'https://example.com',
            'public_key' => 'npk_abc',
            'is_active' => true,
            'prompt_style' => 'card',
        ],
    ]);

    $site = testClient($http)->sites->current();

    expect($http->lastRequest()->uri)->toBe('https://api.test/api/v1/sites/current')
        ->and($site->id)->toBe(3)
        ->and($site->publicKey)->toBe('npk_abc')
        ->and($site->promptStyleEnum())->toBe(PromptStyle::CARD);
});

it('reports a token with no linked site as not found', function () {
    $http = MockHttpClient::make()->willRespond(404, ['message' => 'No site found for this token.']);

    expect(fn () => testClient($http)->sites->current())
        ->toThrow(NotFoundException::class, 'No site found for this token.');
});

it('retrieves a site by id', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => ['id' => 8, 'site_name' => 'Other']]);

    expect(testClient($http)->sites->retrieve(8)->id)->toBe(8)
        ->and($http->lastRequest()->uri)->toBe('https://api.test/api/v1/sites/8');
});

it('updates the current site with prompt settings', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => ['id' => 3, 'site_name' => 'Example']]);

    testClient($http)->sites->updateCurrent(
        SiteUpdate::make('Example', 'https://example.com')
            ->promptTitle('Stay in the loop')
            ->promptButtons('Allow', 'No thanks')
            ->promptStyle(PromptStyle::BANNER)
            ->promptDelaySeconds(5)
            ->removePoweredBy()
    );

    expect($http->lastRequest()->method)->toBe('PUT')
        ->and($http->lastRequest()->uri)->toBe('https://api.test/api/v1/sites/current')
        ->and($http->bodyAt(0))->toBe([
            'site_name' => 'Example',
            'site_url' => 'https://example.com',
            'prompt_title' => 'Stay in the loop',
            'prompt_approve_text' => 'Allow',
            'prompt_cancel_text' => 'No thanks',
            'prompt_style' => 'banner',
            'prompt_delay_seconds' => 5,
            'remove_powered_by' => true,
        ]);
});

it('updates a site by id', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => ['id' => 8, 'site_name' => 'Renamed']]);

    testClient($http)->sites->update(8, SiteUpdate::make('Renamed', 'https://example.com'));

    expect($http->lastRequest()->uri)->toBe('https://api.test/api/v1/sites/8');
});

it('drops null fields from an optional payload group', function () {
    // welcomeNotification() takes optional arguments; the unset ones must not
    // be sent as nulls and blank out server-side values.
    $payload = SiteUpdate::make('Example', 'https://example.com')
        ->welcomeNotification(true, 'Welcome', 'Thanks for subscribing');

    expect($payload->toArray())->toBe([
        'site_name' => 'Example',
        'site_url' => 'https://example.com',
        'welcome_notification_enabled' => true,
        'welcome_notification_title' => 'Welcome',
        'welcome_notification_message' => 'Thanks for subscribing',
    ]);
});
