<?php

declare(strict_types=1);

use NotifyPress\Enum\AutomationStatus;
use NotifyPress\Query\AutomationQuery;
use NotifyPress\Query\NotificationQuery;
use NotifyPress\Query\SegmentQuery;
use NotifyPress\Query\SubscriberQuery;
use NotifyPress\Tests\Support\MockHttpClient;

/*
 * Additions that follow the NotifyPress API: list options, envelope values and the
 * capabilities endpoint.
 */

/**
 * The query string of the request at $index, as an array.
 *
 * @return array<array-key, mixed>
 */
function requestQuery(MockHttpClient $http, int $index = 0): array
{
    parse_str((string) parse_url($http->requestAt($index)->uri, PHP_URL_QUERY), $query);

    return $query;
}

it('reads which send options the plan allows', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => ['canSchedule' => true, 'canRecur' => false]]);

    $capabilities = testClient($http)->notifications->capabilities();

    expect($http->lastRequest()->uri)->toBe('https://api.test/api/v1/notifications/capabilities')
        ->and($capabilities)->toBe(['canSchedule' => true, 'canRecur' => false]);
});

it('treats a missing capabilities payload as nothing allowed', function () {
    $http = MockHttpClient::make()->willRespond(200, []);

    expect(testClient($http)->notifications->capabilities())->toBe(['canSchedule' => false, 'canRecur' => false]);
});

it('asks for full text and leaves out welcome messages on the notification list', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => []]);

    testClient($http)->notifications->list(NotificationQuery::make()->fullText()->excludeWelcome());

    expect(requestQuery($http))->toBe(['full_text' => '1', 'exclude_welcome' => '1']);
});

it('drops a notification flag that is turned back off', function () {
    expect(NotificationQuery::make()->fullText()->fullText(false)->excludeWelcome(false)->toArray())->toBe([]);
});

it('sorts subscribers by registration date and keeps it on the next page', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['data' => [['id' => 1]], 'next_cursor' => 'abc', 'total' => 2, 'active' => 1, 'isPaidUser' => true])
        ->willRespond(200, ['data' => [['id' => 2]], 'next_cursor' => null]);

    $page = testClient($http)->subscribers->list(SubscriberQuery::make()->sortByCreatedAt()->newestFirst());
    $page->nextPage();

    expect(requestQuery($http, 0))->toMatchArray(['sort' => 'created_at', 'order' => 'desc'])
        ->and(requestQuery($http, 1))->toMatchArray(['sort' => 'created_at', 'cursor' => 'abc'])
        ->and($page->total)->toBe(2)
        ->and($page->active)->toBe(1)
        ->and($page->isPaidUser())->toBeTrue();
});

it('filters and searches automations and reads the status counts', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [],
        'current_page' => 1,
        'last_page' => 1,
        'per_page' => 20,
        'total' => 0,
        'statusCounts' => ['all' => 7, 'active' => 3, 'paused' => 1, 'inactive' => 2],
    ]);

    $page = testClient($http)->automations->list(AutomationQuery::make()->status(AutomationStatus::ACTIVE)->search('welcome'));

    expect(requestQuery($http))->toBe(['status' => 'active', 'q' => 'welcome'])
        ->and($page->statusCounts())->toBe(['all' => 7, 'active' => 3, 'paused' => 1, 'inactive' => 2]);
});

it('reads zero status counts when the API does not send them', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => [], 'current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0]);

    expect(testClient($http)->automations->list()->statusCounts())->toBe(['all' => 0, 'active' => 0, 'paused' => 0, 'inactive' => 0]);
});

it('pages through every segment instead of stopping at the first ten', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, [
            'data' => [['id' => 1, 'name' => 'US', 'subscriber_count' => 120, 'subscriber_count_computed_at' => '2026-11-05T10:00:00+00:00', 'rule' => ['tags' => ['condition' => 'is_one_of', 'values' => ['4', '9']]]]],
            'meta' => ['next_cursor' => 'next', 'per_page' => 1],
            'isPaidUser' => true,
        ])
        ->willRespond(200, ['data' => [['id' => 2, 'name' => 'EU']], 'meta' => ['next_cursor' => null]]);

    $segments = testClient($http)->segments->list(SegmentQuery::make()->perPage(100));
    $all = iterator_to_array($segments->autoPaging(), false);

    expect(requestQuery($http, 0))->toBe(['per_page' => '100'])
        ->and(requestQuery($http, 1))->toBe(['per_page' => '100', 'cursor' => 'next'])
        ->and(array_map(fn ($segment) => $segment->name, $all))->toBe(['US', 'EU'])
        ->and($segments->isPaidUser())->toBeTrue()
        ->and($all[0]->subscriberCount)->toBe(120)
        ->and($all[0]->subscriberCountComputedAt)->toBe('2026-11-05T10:00:00+00:00')
        ->and($all[0]->tags())->toBe(['4', '9'])
        ->and($all[1]->subscriberCount)->toBeNull();
});
