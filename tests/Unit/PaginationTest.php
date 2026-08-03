<?php

declare(strict_types=1);

use NotifyPress\Model\Site;
use NotifyPress\Model\Subscriber;
use NotifyPress\Query\SubscriberQuery;
use NotifyPress\Tests\Support\MockHttpClient;

it('reads cursors from the top level of the payload', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [['id' => 1, 'token' => 'a'], ['id' => 2, 'token' => 'b']],
        'next_cursor' => 'page-2',
        'prev_cursor' => null,
        'per_page' => 10,
        'total' => 57,
    ]);

    $page = testClient($http)->subscribers->list();

    expect($page->nextCursor)->toBe('page-2')
        ->and($page->prevCursor)->toBeNull()
        ->and($page->perPage)->toBe(10)
        ->and($page->total)->toBe(57)
        ->and($page)->toHaveCount(2)
        ->and($page->hasNextPage())->toBeTrue()
        ->and($page->data[0])->toBeInstanceOf(Subscriber::class)
        ->and($page->data[0]->token)->toBe('a');
});

it('reads cursors nested under meta, as GET /sites returns them', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [['id' => 1, 'site_name' => 'Example']],
        'meta' => ['next_cursor' => 'page-2', 'prev_cursor' => 'page-0', 'per_page' => 10],
    ]);

    $page = testClient($http)->sites->list();

    expect($page->nextCursor)->toBe('page-2')
        ->and($page->prevCursor)->toBe('page-0')
        ->and($page->perPage)->toBe(10)
        ->and($page->data[0])->toBeInstanceOf(Site::class);
});

it('treats a missing or empty cursor as the end of the set', function (array $payload) {
    /** @var array<string, mixed> $payload */
    $http = MockHttpClient::make()->willRespond(200, $payload);

    $page = testClient($http)->subscribers->list();

    expect($page->nextCursor)->toBeNull()
        ->and($page->hasNextPage())->toBeFalse()
        ->and($page->nextPage())->toBeNull();
})->with([
    'absent' => [['data' => []]],
    'null' => [['data' => [], 'next_cursor' => null]],
    'empty string' => [['data' => [], 'next_cursor' => '']],
]);

it('iterates only the current page by default', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [['id' => 1, 'token' => 'a'], ['id' => 2, 'token' => 'b']],
        'next_cursor' => 'page-2',
    ]);

    $tokens = [];

    foreach (testClient($http)->subscribers->list() as $subscriber) {
        $tokens[] = $subscriber->token;
    }

    // One page, one request: walking the whole set must be an explicit choice.
    expect($tokens)->toBe(['a', 'b'])
        ->and($http->callCount())->toBe(1);
});

it('walks every page with autoPaging, preserving the original filters', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['data' => [['id' => 1, 'token' => 'a']], 'next_cursor' => 'page-2'])
        ->willRespond(200, ['data' => [['id' => 2, 'token' => 'b']], 'next_cursor' => 'page-3'])
        ->willRespond(200, ['data' => [['id' => 3, 'token' => 'c']], 'next_cursor' => null]);

    $tokens = [];

    foreach (testClient($http)->subscribers->list(SubscriberQuery::make()->limit(1)->newestFirst())->autoPaging() as $subscriber) {
        $tokens[] = $subscriber->token;
    }

    expect($tokens)->toBe(['a', 'b', 'c'])
        ->and($http->callCount())->toBe(3)
        ->and(queryOf($http->requestAt(0)->uri))->toBe(['limit' => '1', 'order' => 'desc'])
        ->and(queryOf($http->requestAt(1)->uri))->toBe(['limit' => '1', 'order' => 'desc', 'cursor' => 'page-2'])
        ->and(queryOf($http->requestAt(2)->uri))->toBe(['limit' => '1', 'order' => 'desc', 'cursor' => 'page-3']);
});

it('collects every page into one array with all()', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['data' => [['id' => 1, 'token' => 'a']], 'next_cursor' => 'page-2'])
        ->willRespond(200, ['data' => [['id' => 2, 'token' => 'b']]]);

    expect(testClient($http)->subscribers->list()->all())->toHaveCount(2);
});

it('fetches pages lazily, only when the previous one runs out', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['data' => [['id' => 1, 'token' => 'a']], 'next_cursor' => 'page-2'])
        ->willRespond(200, ['data' => [['id' => 2, 'token' => 'b']]]);

    $iterator = testClient($http)->subscribers->list()->autoPaging();
    $iterator->current();

    expect($http->callCount())->toBe(1);

    $iterator->next();
    $iterator->current();

    expect($http->callCount())->toBe(2);
});

it('steps forward one page at a time', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['data' => [['id' => 1, 'token' => 'a']], 'next_cursor' => 'page-2'])
        ->willRespond(200, ['data' => [['id' => 2, 'token' => 'b']]]);

    $first = testClient($http)->subscribers->list();
    $second = $first->nextPage();

    expect($second)->not->toBeNull()
        ->and($second?->data[0]->token)->toBe('b')
        ->and($second?->hasNextPage())->toBeFalse();
});

it('reports an empty page as empty', function () {
    $http = MockHttpClient::make()->willRespond(200, ['data' => []]);

    $page = testClient($http)->subscribers->list();

    expect($page->isEmpty())->toBeTrue()
        ->and($page)->toHaveCount(0)
        ->and($page->all())->toBe([]);
});

it('skips malformed rows rather than fataling', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [['id' => 1, 'token' => 'a'], 'not-an-object', ['id' => 2, 'token' => 'b']],
    ]);

    expect(testClient($http)->subscribers->list())->toHaveCount(2);
});

it('paginates automations by page number, not cursor', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, [
            'data' => [['id' => 1, 'name' => 'Welcome']],
            'current_page' => 1,
            'last_page' => 2,
            'per_page' => 1,
            'total' => 2,
            'isPaidUser' => true,
        ])
        ->willRespond(200, [
            'data' => [['id' => 2, 'name' => 'Winback']],
            'current_page' => 2,
            'last_page' => 2,
            'per_page' => 1,
            'total' => 2,
        ]);

    $page = testClient($http)->automations->list(['per_page' => 1]);

    expect($page->currentPage)->toBe(1)
        ->and($page->lastPage)->toBe(2)
        ->and($page->total)->toBe(2)
        ->and($page->isPaidUser())->toBeTrue()
        ->and($page->hasNextPage())->toBeTrue();

    $names = [];

    foreach ($page->autoPaging() as $automation) {
        $names[] = $automation->name;
    }

    expect($names)->toBe(['Welcome', 'Winback'])
        ->and(queryOf($http->requestAt(1)->uri))->toBe(['per_page' => '1', 'page' => '2']);
});

it('stops offset pagination on the last page', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [['id' => 1, 'name' => 'Welcome']],
        'current_page' => 2,
        'last_page' => 2,
        'per_page' => 10,
        'total' => 11,
    ]);

    $page = testClient($http)->automations->list();

    expect($page->hasNextPage())->toBeFalse()
        ->and($page->nextPage())->toBeNull()
        ->and($page->isPaidUser())->toBeFalse();
});

it('keeps the envelope around an unpaginated collection', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [['id' => 1, 'title' => 'New feature', 'body' => 'Details']],
        'unread_count' => 3,
    ]);

    $announcements = testClient($http)->announcements->list();

    expect($announcements)->toHaveCount(1)
        ->and($announcements->meta('unread_count'))->toBe(3)
        ->and($announcements->meta('missing', 'fallback'))->toBe('fallback')
        ->and($announcements->first()?->title)->toBe('New feature');
});
