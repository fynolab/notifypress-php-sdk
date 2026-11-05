<?php

declare(strict_types=1);

use NotifyPress\Enum\NotificationStatus;
use NotifyPress\Http\Multipart\MultipartBody;
use NotifyPress\Model\Notification;
use NotifyPress\Payload\NotificationDraft;
use NotifyPress\Payload\RecurringSchedule;
use NotifyPress\Payload\ScheduleNotification;
use NotifyPress\Payload\SendNotification;
use NotifyPress\Query\NotificationQuery;
use NotifyPress\Resource\Notifications;
use NotifyPress\Tests\Support\MockHttpClient;

it('sends a notification', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'message' => 'Notification queued.',
        'notification' => ['id' => 9, 'title' => 'Flash sale', 'message' => '25% off', 'status' => 'sending'],
        'subscriber_total' => 1200,
    ]);

    $result = testClient($http)->notifications->send(
        SendNotification::make('Flash sale', '25% off')
            ->targetUrl('https://example.com/sale')
            ->toSegment(4)
    );

    expect($http->lastRequest()->method)->toBe('POST')
        ->and($http->lastRequest()->uri)->toBe('https://api.test/api/v1/notifications/send')
        ->and($http->bodyAt(0))->toBe([
            'title' => 'Flash sale',
            'message' => '25% off',
            'target_url' => 'https://example.com/sale',
            'segment_id' => 4,
        ])
        ->and($result->message)->toBe('Notification queued.')
        ->and($result->subscriberTotal)->toBe(1200)
        ->and($result->notification?->statusEnum())->toBe(NotificationStatus::SENDING);
});

it('targets a single subscriber through the subscription token header', function () {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'Sent.']);

    testClient($http)->notifications->send(
        SendNotification::make('Hi', 'There'),
        'fcm-token-abc'
    );

    expect($http->lastRequest()->headers[Notifications::SUBSCRIPTION_TOKEN_HEADER])->toBe('fcm-token-abc')
        ->and($http->bodyAt(0))->not->toHaveKey('subscription_token');
});

it('omits the subscription token header for an audience-wide send', function () {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'Sent.']);

    testClient($http)->notifications->send(SendNotification::make('Hi', 'There'));

    expect($http->lastRequest()->headers)->not->toHaveKey(Notifications::SUBSCRIPTION_TOKEN_HEADER);
});

it('accepts a plain array instead of a payload builder', function () {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'Sent.']);

    testClient($http)->notifications->send(['title' => 'Hi', 'message' => 'There']);

    expect($http->bodyAt(0))->toBe(['title' => 'Hi', 'message' => 'There']);
});

it('lists notifications with filters', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [['id' => 1, 'title' => 'One', 'message' => 'Body', 'status' => 'sent']],
    ]);

    $page = testClient($http)->notifications->list(
        NotificationQuery::make()->status(NotificationStatus::SENT)->limit(25)->oldestFirst()
    );

    expect(queryOf($http->lastRequest()->uri))->toBe(['status' => 'sent', 'limit' => '25', 'order' => 'asc'])
        ->and($page->data[0])->toBeInstanceOf(Notification::class)
        ->and($page->data[0]->isSent())->toBeTrue();
});

it('creates a draft', function () {
    $http = MockHttpClient::make()->willRespond(201, [
        'message' => 'Created.',
        'data' => ['id' => 5, 'title' => 'Draft', 'message' => 'Body'],
    ]);

    $notification = testClient($http)->notifications->create(
        NotificationDraft::make('Draft', 'Body')->toEveryone()->iconUrl('https://example.com/i.png')
    );

    expect($http->lastRequest()->uri)->toBe('https://api.test/api/v1/notifications')
        ->and($http->bodyAt(0))->toBe([
            'title' => 'Draft',
            'message' => 'Body',
            'segment_id' => 'all',
            'icon_url' => 'https://example.com/i.png',
        ])
        ->and($notification->id)->toBe(5);
});

it('creates a draft with an uploaded icon', function () {
    $http = MockHttpClient::make()->willRespond(201, ['data' => ['id' => 5, 'title' => 'T', 'message' => 'M']]);

    testClient($http)->notifications->create(
        MultipartBody::make('BOUND')
            ->field('title', 'T')
            ->field('message', 'M')
            ->file('icon', str_repeat('x', 64), 'icon.png')
    );

    $body = $http->lastRequest()->body ?? '';

    expect($http->lastRequest()->headers['Content-Type'])->toBe('multipart/form-data; boundary=BOUND')
        ->and($body)->toContain('name="title"')
        ->and($body)->toContain('filename="icon.png"')
        ->and($body)->toContain('Content-Type: image/png');
});

it('retrieves a notification with its analytics', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [
            'id' => 5,
            'title' => 'Sent one',
            'message' => 'Body',
            'status' => 'sent',
            'analytics' => [
                'views' => 100,
                'clicks' => 25,
                'click_through_rate' => 25,
                'browsers' => ['chrome' => 80],
                'devices' => ['desktop' => 60],
            ],
        ],
    ]);

    $notification = testClient($http)->notifications->retrieve(5);

    expect($notification->analytics?->clicks)->toBe(25)
        ->and($notification->analytics?->chrome())->toBe(80)
        ->and($notification->analytics?->desktop())->toBe(60);
});

it('schedules a new notification', function () {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'Scheduled.']);

    testClient($http)->notifications->schedule(
        ScheduleNotification::make('Morning', 'Rise and shine')
            ->at(new DateTimeImmutable('2026-08-15 09:30:00', new DateTimeZone('Asia/Dhaka')))
    );

    expect($http->bodyAt(0))->toBe([
        'title' => 'Morning',
        'message' => 'Rise and shine',
        'scheduled_at' => '2026-08-15T09:30:00+06:00',
    ]);
});

it('sends a scheduled draft time with its offset, so the instant is exact', function () {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'Scheduled.']);

    // The DateTime's own timezone decides the moment; the offset travels with it.
    testClient($http)->notifications->scheduleDraft(
        7,
        new DateTimeImmutable('2026-11-20 09:30:00', new DateTimeZone('America/Toronto'))
    );

    expect($http->lastRequest()->uri)->toBe('https://api.test/api/v1/notifications/7/schedule')
        ->and($http->bodyAt(0))->toBe(['scheduled_at' => '2026-11-20T09:30:00-05:00']);
});

it('uses the daylight saving offset of the date itself', function () {
    expect(ScheduleNotification::make('T', 'M')->at(new DateTimeImmutable('2026-07-01 09:30:00', new DateTimeZone('America/Toronto')))->toArray()['scheduled_at'])
        ->toBe('2026-07-01T09:30:00-04:00');
});

it('accepts a pre-formatted scheduled time that carries its offset', function () {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'Scheduled.']);

    testClient($http)->notifications->scheduleDraft(7, '2026-08-15T03:30:00Z');

    expect($http->bodyAt(0))->toBe(['scheduled_at' => '2026-08-15T03:30:00Z']);
});

it('rejects a scheduled time without an offset before sending anything', function () {
    $http = MockHttpClient::make();

    expect(fn () => testClient($http)->notifications->scheduleDraft(7, '2026-08-15 09:30:00'))
        ->toThrow(InvalidArgumentException::class, 'timezone offset')
        ->and(fn () => ScheduleNotification::make('T', 'M')->scheduledAt('2026-08-15T09:30:00'))
        ->toThrow(InvalidArgumentException::class);

    expect($http->callCount())->toBe(0);
});

it('builds each recurring frequency with only the fields it needs', function () {
    expect(RecurringSchedule::daily('09:30')->toArray())
        ->toBe(['frequency' => 'daily', 'time' => '09:30'])
        ->and(RecurringSchedule::weekly('09:30')->onDays([1, 3, 5])->toArray())
        ->toBe(['frequency' => 'weekly', 'time' => '09:30', 'days' => [1, 3, 5]])
        ->and(RecurringSchedule::monthly('09:30')->onDayOfMonth(15)->toArray())
        ->toBe(['frequency' => 'monthly', 'time' => '09:30', 'day_of_month' => 15]);
});

it('sends the timezone a recurring time is in', function () {
    expect(RecurringSchedule::daily('09:30')->inTimezone('Asia/Dhaka')->toArray())
        ->toBe(['frequency' => 'daily', 'time' => '09:30', 'timezone' => 'Asia/Dhaka']);
});

it('starts a recurring send', function () {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'Recurring send scheduled.']);

    testClient($http)->notifications->recurringSend(7, RecurringSchedule::weekly('09:30')->onDays([1]));

    expect($http->lastRequest()->uri)->toBe('https://api.test/api/v1/notifications/7/recurring-send')
        ->and($http->bodyAt(0))->toBe(['frequency' => 'weekly', 'time' => '09:30', 'days' => [1]]);
});

it('cancels, deletes, and reads live activity', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['message' => 'Cancelled.'])
        ->willRespond(200, ['message' => 'Deleted.'])
        ->willRespond(200, ['sends' => []]);

    $client = testClient($http);

    expect($client->notifications->cancel(3))->toBe(['message' => 'Cancelled.'])
        ->and($client->notifications->delete(3))->toBe(['message' => 'Deleted.'])
        ->and($client->notifications->liveActivity())->toBe(['sends' => []]);

    expect($http->requestAt(0)->uri)->toBe('https://api.test/api/v1/notifications/3/cancel')
        ->and($http->requestAt(1)->method)->toBe('DELETE')
        ->and($http->requestAt(2)->uri)->toBe('https://api.test/api/v1/notifications/live-activity');
});

it('tracks clicks and views', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['message' => 'ok', 'clicks' => 5])
        ->willRespond(200, ['message' => 'ok', 'views' => 9]);

    $client = testClient($http);
    $client->notifications->trackClick(3);
    $client->notifications->trackView(3, ['browser' => 'Chrome']);

    expect($http->bodyAt(0))->toBe(['clicked' => true])
        ->and($http->bodyAt(1))->toBe(['viewed' => true, 'browser' => 'Chrome']);
});
