<?php

declare(strict_types=1);

use NotifyPress\Config;
use NotifyPress\Enum\NotificationStatus;
use NotifyPress\Payload\NotificationDraft;
use NotifyPress\Payload\SendNotification;
use NotifyPress\Payload\SubscriberCreate;
use NotifyPress\Payload\TagCreate;
use NotifyPress\Query\NotificationQuery;
use NotifyPress\Query\SubscriberQuery;
use NotifyPress\Tests\Support\MockHttpClient;

it('never mutates the payload it was called on', function () {
    $base = SendNotification::make('Title', 'Body');
    $withUrl = $base->targetUrl('https://example.com');

    expect($base->toArray())->toBe(['title' => 'Title', 'message' => 'Body'])
        ->and($withUrl->toArray())->toHaveKey('target_url')
        ->and($base)->not->toBe($withUrl);
});

it('lets one base payload be specialised several ways', function () {
    $base = SendNotification::make('Title', 'Body');

    expect($base->toSegment(4)->toArray()['segment_id'])->toBe(4)
        ->and($base->toEveryone()->toArray()['segment_id'])->toBe('all')
        ->and($base->toArray())->not->toHaveKey('segment_id');
});

it('drops null values so they cannot blank out server-side fields', function () {
    $payload = SendNotification::make('Title', 'Body')->set('icon_url', null)->set('target_url', 'https://x');

    expect($payload->toArray())->toBe([
        'title' => 'Title',
        'message' => 'Body',
        'target_url' => 'https://x',
    ]);
});

it('takes an arbitrary field the SDK does not model yet', function () {
    $payload = SendNotification::make('Title', 'Body')->set('ttl_seconds', 300);

    expect($payload->toArray()['ttl_seconds'])->toBe(300);
});

it('merges a batch of fields at once', function () {
    $payload = SendNotification::make('Title', 'Body')->merge(['segment_id' => 4, 'target_url' => 'https://x']);

    expect($payload->toArray())->toBe([
        'title' => 'Title',
        'message' => 'Body',
        'segment_id' => 4,
        'target_url' => 'https://x',
    ]);
});

it('accepts an enum or its raw string wherever one is expected', function () {
    expect(NotificationDraft::make('T', 'M')->status(NotificationStatus::PENDING)->toArray()['status'])
        ->toBe('pending')
        ->and(NotificationDraft::make('T', 'M')->status('pending')->toArray()['status'])
        ->toBe('pending');
});

it('publishes the server-side length limits it mirrors', function () {
    expect(SendNotification::MAX_TITLE_LENGTH)->toBe(50)
        ->and(SendNotification::MAX_MESSAGE_LENGTH)->toBe(100)
        ->and(NotificationDraft::MAX_MESSAGE_LENGTH)->toBe(150);
});

it('builds subscriber and tag payloads fluently', function () {
    expect(SubscriberCreate::make('fcm-abc')->country('CA')->inactive()->toArray())
        ->toBe(['token' => 'fcm-abc', 'country' => 'CA', 'active' => false])
        ->and(TagCreate::category('News')->toArray())
        ->toBe(['name' => 'News', 'type' => 'category']);
});

it('never mutates the query it was called on', function () {
    $base = NotificationQuery::make()->limit(10);
    $filtered = $base->status(NotificationStatus::SENT);

    expect($base->toArray())->toBe(['limit' => 10])
        ->and($filtered->toArray())->toBe(['limit' => 10, 'status' => 'sent']);
});

it('expresses sort order both ways', function () {
    expect(SubscriberQuery::make()->newestFirst()->toArray())->toBe(['order' => 'desc'])
        ->and(SubscriberQuery::make()->oldestFirst()->toArray())->toBe(['order' => 'asc'])
        ->and(SubscriberQuery::make()->order('asc')->toArray())->toBe(['order' => 'asc']);
});

it('scopes a subscriber query to one site', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => []]);

    testClient($http)->subscribers->list(SubscriberQuery::make()->forSite(7)->limit(100));

    expect(queryOf($http->lastRequest()->uri))->toBe(['site_id' => '7', 'limit' => '100']);
});

it('keeps the config immutable across with* calls', function () {
    $base = Config::make('1|secret');
    $tuned = $base->withTimeout(5.0)->withMaxRetries(4)->withBaseUrl('https://staging.test/api/v1');

    expect($base->timeout)->toBe(30.0)
        ->and($base->maxRetries)->toBe(2)
        ->and($base->baseUrl)->toBe(Config::DEFAULT_BASE_URL)
        ->and($tuned->timeout)->toBe(5.0)
        ->and($tuned->maxRetries)->toBe(4)
        ->and($tuned->baseUrl)->toBe('https://staging.test/api/v1')
        ->and($tuned->apiToken)->toBe('1|secret');
});

it('accumulates default headers rather than replacing them', function () {
    $config = Config::make('1|secret')->withHeader('X-One', 'a')->withHeader('X-Two', 'b');

    expect($config->defaultHeaders)->toBe(['X-One' => 'a', 'X-Two' => 'b']);
});
