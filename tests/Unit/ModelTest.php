<?php

declare(strict_types=1);

use NotifyPress\Enum\AutomationStatus;
use NotifyPress\Enum\AutomationStepType;
use NotifyPress\Enum\NotificationStatus;
use NotifyPress\Model\Automation;
use NotifyPress\Model\Notification;
use NotifyPress\Model\Site;
use NotifyPress\Model\Subscriber;
use NotifyPress\Model\Tag;

it('reads a known enum value off a model', function () {
    $notification = Notification::fromArray(['id' => 1, 'title' => 'T', 'message' => 'M', 'status' => 'scheduled']);

    expect($notification->statusEnum())->toBe(NotificationStatus::SCHEDULED)
        ->and($notification->isScheduled())->toBeTrue()
        ->and($notification->isSent())->toBeFalse();
});

it('survives an enum value the server added after this release', function () {
    // A new status must never fatal an older SDK: the raw string stays
    // readable even though the typed accessor cannot name it.
    $notification = Notification::fromArray([
        'id' => 1,
        'title' => 'T',
        'message' => 'M',
        'status' => 'throttled_by_quota',
    ]);

    expect($notification->status)->toBe('throttled_by_quota')
        ->and($notification->statusEnum())->toBeNull();
});

it('leaves the typed accessor null when the field is missing', function () {
    $automation = Automation::fromArray(['id' => 1, 'name' => 'X']);

    expect($automation->statusEnum())->toBeNull()
        ->and($automation->triggerEnum())->toBeNull()
        ->and($automation->isActive())->toBeFalse();
});

it('recognises every status the server can send today', function (string $value, AutomationStatus $expected) {
    expect(Automation::fromArray(['id' => 1, 'name' => 'X', 'status' => $value])->statusEnum())
        ->toBe($expected);
})->with([
    ['draft', AutomationStatus::DRAFT],
    ['active', AutomationStatus::ACTIVE],
    ['paused', AutomationStatus::PAUSED],
    ['archived', AutomationStatus::ARCHIVED],
]);

it('marks the step types the engine does not run yet as unsupported', function () {
    expect(AutomationStepType::WAIT->isSupported())->toBeTrue()
        ->and(AutomationStepType::SEND_NOTIFICATION->isSupported())->toBeTrue()
        ->and(AutomationStepType::ADD_TAG->isSupported())->toBeTrue()
        ->and(AutomationStepType::WEBHOOK->isSupported())->toBeTrue()
        ->and(AutomationStepType::CONDITION->isSupported())->toBeFalse()
        ->and(AutomationStepType::AB_SPLIT->isSupported())->toBeFalse();
});

it('keeps fields the SDK does not model yet', function () {
    $site = Site::fromArray([
        'id' => 3,
        'site_name' => 'Example',
        'public_key' => 'npk_abc',
        'future_feature' => ['enabled' => true, 'quota' => 5],
    ]);

    expect($site->get('future_feature.quota'))->toBe(5)
        ->and($site->get('future_feature.enabled'))->toBeTrue()
        ->and($site->toArray())->toHaveKey('future_feature');
});

it('returns the default for an unknown dot path', function () {
    $site = Site::fromArray(['id' => 3]);

    expect($site->get('nothing.here'))->toBeNull()
        ->and($site->get('nothing.here', 'fallback'))->toBe('fallback')
        ->and($site->get('id.nested', 'fallback'))->toBe('fallback');
});

it('coerces loose JSON scalars into the declared types', function () {
    // The API sends counts as strings in places; an SDK that trusted the JSON
    // types would hand callers a string where an int is declared.
    $tag = Tag::fromArray(['id' => '4', 'name' => 'News', 'subscribers_count' => '120']);

    expect($tag->id)->toBe(4)
        ->and($tag->subscribersCount)->toBe(120);
});

it('defaults missing fields instead of fataling', function () {
    $subscriber = Subscriber::fromArray([]);

    expect($subscriber->id)->toBe(0)
        ->and($subscriber->token)->toBe('')
        ->and($subscriber->active)->toBeFalse()
        ->and($subscriber->tags)->toBe([])
        ->and($subscriber->country)->toBeNull();
});

it('keeps response datetimes as ISO strings and parses them on request', function () {
    // Every timestamp on responses is ISO 8601 in UTC.
    $notification = Notification::fromArray([
        'id' => 1,
        'title' => 'T',
        'message' => 'M',
        'scheduled_at' => '2026-08-15T03:30:00+00:00',
        'sent_at' => null,
        'recurring_timezone' => 'Asia/Dhaka',
    ]);

    expect($notification->scheduledAt)->toBe('2026-08-15T03:30:00+00:00')
        ->and($notification->scheduledAtDate()?->setTimezone(new DateTimeZone('Asia/Dhaka'))->format('Y-m-d H:i'))->toBe('2026-08-15 09:30')
        ->and($notification->sentAtDate())->toBeNull()
        ->and($notification->recurringTimezone)->toBe('Asia/Dhaka');
});
