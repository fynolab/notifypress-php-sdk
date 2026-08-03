<?php

declare(strict_types=1);

use NotifyPress\Enum\SegmentCondition;
use NotifyPress\Enum\TagType;
use NotifyPress\Payload\SegmentPayload;
use NotifyPress\Payload\SegmentRule;
use NotifyPress\Payload\SubscriberCreate;
use NotifyPress\Payload\TagCreate;
use NotifyPress\Tests\Support\MockHttpClient;

it('creates a subscriber', function () {
    $http = MockHttpClient::make()->willRespond(201, [
        'data' => ['id' => 11, 'token' => 'fcm-abc', 'active' => true, 'country' => 'CA'],
    ]);

    $subscriber = testClient($http)->subscribers->create(
        SubscriberCreate::make('fcm-abc')->country('CA')->city('Toronto')->browser('Chrome')
    );

    expect($http->bodyAt(0))->toBe([
        'token' => 'fcm-abc',
        'country' => 'CA',
        'city' => 'Toronto',
        'browser' => 'Chrome',
    ])
        ->and($subscriber->id)->toBe(11)
        ->and($subscriber->active)->toBeTrue();
});

it('deletes a subscriber by numeric id, not by token', function () {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'Deleted.']);

    testClient($http)->subscribers->delete(11);

    expect($http->lastRequest()->method)->toBe('DELETE')
        ->and($http->lastRequest()->uri)->toBe('https://api.test/api/v1/subscribers/11');
});

it('addresses the tag endpoints by token, and escapes it', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['message' => 'Tag added.', 'data' => []])
        ->willRespond(200, ['message' => 'Tag removed.']);

    $client = testClient($http);
    $client->subscribers->addTag('fcm/token+with spaces', 4);
    $client->subscribers->removeTag('fcm/token+with spaces', 4);

    expect($http->requestAt(0)->uri)
        ->toBe('https://api.test/api/v1/subscribers/fcm%2Ftoken%2Bwith%20spaces/tags')
        ->and($http->bodyAt(0))->toBe(['tag_id' => 4])
        ->and($http->requestAt(1)->method)->toBe('DELETE')
        ->and($http->requestAt(1)->uri)
        ->toBe('https://api.test/api/v1/subscribers/fcm%2Ftoken%2Bwith%20spaces/tags/4');
});

it('lists tags with their subscriber counts', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [
            ['id' => 1, 'name' => 'News', 'type' => 'category', 'subscribers_count' => 120],
            ['id' => 2, 'name' => 'Deals', 'type' => 'interest', 'subscribers_count' => 40],
        ],
    ]);

    $tags = testClient($http)->tags->list();

    expect($tags)->toHaveCount(2)
        ->and($tags->first()?->subscribersCount)->toBe(120)
        ->and($tags->first()?->typeEnum())->toBe(TagType::CATEGORY);
});

it('creates a typed tag', function () {
    $http = MockHttpClient::make()->willRespond(201, ['data' => ['id' => 3, 'name' => 'Deals']]);

    testClient($http)->tags->create(TagCreate::interest('Deals'));

    expect($http->bodyAt(0))->toBe(['name' => 'Deals', 'type' => 'interest']);
});

it('deletes a tag', function () {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'Tag deleted.']);

    testClient($http)->tags->delete(3);

    expect($http->lastRequest()->method)->toBe('DELETE')
        ->and($http->lastRequest()->uri)->toBe('https://api.test/api/v1/tags/3');
});

it('builds a segment rule from its condition groups', function () {
    $rule = SegmentRule::make()
        ->location(['US', 'CA'], SegmentCondition::IS_ONE_OF)
        ->device(['Desktop'])
        ->browser(['Chrome', 'Firefox'], SegmentCondition::IS_ONE_OF);

    expect($rule->toArray())->toBe([
        'location' => ['condition' => 'is_one_of', 'values' => ['US', 'CA']],
        'device' => ['condition' => 'is_equal_to', 'values' => ['Desktop']],
        'browser' => ['condition' => 'is_one_of', 'values' => ['Chrome', 'Firefox']],
    ]);
});

it('creates a segment', function () {
    $http = MockHttpClient::make()->willRespond(201, [
        'data' => [
            'id' => 6,
            'name' => 'Canadians',
            'is_active' => true,
            'rule' => ['location' => ['condition' => 'is_equal_to', 'values' => ['CA']]],
        ],
    ]);

    $segment = testClient($http)->segments->create(
        SegmentPayload::make('Canadians')->rule(SegmentRule::make()->location(['CA']))
    );

    expect($http->bodyAt(0))->toBe([
        'name' => 'Canadians',
        'is_active' => true,
        'rule' => ['location' => ['condition' => 'is_equal_to', 'values' => ['CA']]],
    ])
        ->and($segment->countries())->toBe(['CA']);
});

it('reports the plan gate alongside the segment list', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [['id' => 1, 'name' => 'All Canadians']],
        'isPaidUser' => false,
    ]);

    $segments = testClient($http)->segments->list();

    expect($segments)->toHaveCount(1)
        ->and($segments->isPaidUser())->toBeFalse();
});

it('previews how many subscribers a rule matches', function () {
    $http = MockHttpClient::make()->willRespond(200, ['count' => 4210]);

    $count = testClient($http)->segments->previewCount(SegmentRule::make()->device(['Mobile']));

    expect($count)->toBe(4210)
        ->and($http->bodyAt(0))->toBe([
            'rule' => ['device' => ['condition' => 'is_equal_to', 'values' => ['Mobile']]],
        ]);
});

it('reads a missing preview count as zero', function () {
    $http = MockHttpClient::make()->willRespond(200, []);

    expect(testClient($http)->segments->previewCount([]))->toBe(0);
});

it('updates and deletes a segment, and reads the filter metadata', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['data' => ['id' => 6, 'name' => 'Renamed']])
        ->willRespond(200, ['message' => 'Deleted.'])
        ->willRespond(200, ['countries' => ['CA', 'US']]);

    $client = testClient($http);
    $client->segments->update(6, SegmentPayload::make('Renamed')->inactive());
    $client->segments->delete(6);

    expect($client->segments->meta())->toBe(['countries' => ['CA', 'US']])
        ->and($http->bodyAt(0))->toBe(['name' => 'Renamed', 'is_active' => false])
        ->and($http->requestAt(0)->method)->toBe('PUT')
        ->and($http->requestAt(1)->method)->toBe('DELETE')
        ->and($http->requestAt(2)->uri)->toBe('https://api.test/api/v1/segments/meta');
});
