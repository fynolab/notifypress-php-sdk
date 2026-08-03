<?php

declare(strict_types=1);

use NotifyPress\Enum\AutomationStatus;
use NotifyPress\Enum\AutomationTrigger;
use NotifyPress\Exception\PaidPlanException;
use NotifyPress\Exception\ValidationException;
use NotifyPress\Model\Notification;
use NotifyPress\Payload\AutomationPayload;
use NotifyPress\Tests\Support\MockHttpClient;

it('builds a workflow as an ordered list of steps', function () {
    $payload = AutomationPayload::onSubscriberCreated('Welcome series')
        ->description('Greets every new subscriber')
        ->wait(60)
        ->sendNotification(42)
        ->addTag(7)
        ->webhook('https://example.com/hooks/np');

    expect($payload->toArray())->toBe([
        'name' => 'Welcome series',
        'trigger_type' => 'subscriber_created',
        'steps' => [
            ['type' => 'wait', 'config' => ['minutes' => 60]],
            ['type' => 'send_notification', 'config' => ['notification_id' => 42]],
            ['type' => 'add_tag', 'config' => ['tag_id' => 7]],
            ['type' => 'webhook', 'config' => ['url' => 'https://example.com/hooks/np']],
        ],
        'description' => 'Greets every new subscriber',
    ]);
});

it('carries the event name for a custom-event trigger', function () {
    $payload = AutomationPayload::onCustomEvent('Cart abandoned', 'cart_abandoned')->wait(30);

    expect($payload->toArray()['trigger_type'])->toBe('custom_event')
        ->and($payload->toArray()['trigger_config'])->toBe(['event' => 'cart_abandoned']);
});

it('creates an automation', function () {
    $http = MockHttpClient::make()->willRespond(201, [
        'data' => [
            'id' => 12,
            'name' => 'Welcome series',
            'trigger_type' => 'subscriber_created',
            'status' => 'draft',
            'steps' => [['id' => 1, 'type' => 'wait']],
        ],
    ]);

    $automation = testClient($http)->automations->create(
        AutomationPayload::onSubscriberCreated('Welcome series')->wait(60)
    );

    expect($automation->id)->toBe(12)
        ->and($automation->statusEnum())->toBe(AutomationStatus::DRAFT)
        ->and($automation->triggerEnum())->toBe(AutomationTrigger::SUBSCRIBER_CREATED)
        ->and($automation->isActive())->toBeFalse()
        ->and($automation->steps)->toHaveCount(1);
});

it('fires a custom event for a subscriber', function () {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'Enrolled.', 'enrolled' => 1]);

    testClient($http)->automations->trigger('cart_abandoned', 'fcm-abc');

    expect($http->lastRequest()->uri)->toBe('https://api.test/api/v1/automations/trigger')
        ->and($http->bodyAt(0))->toBe(['event' => 'cart_abandoned', 'subscriber_token' => 'fcm-abc']);
});

it('walks the lifecycle endpoints', function (string $action) {
    $http = MockHttpClient::make()->willRespond(200, ['message' => 'ok']);
    $automations = testClient($http)->automations;

    $call = match ($action) {
        'activate' => $automations->activate(...),
        'pause' => $automations->pause(...),
        'archive' => $automations->archive(...),
        default => $automations->duplicate(...),
    };

    $call(12);

    expect($http->lastRequest()->method)->toBe('POST')
        ->and($http->lastRequest()->uri)->toBe('https://api.test/api/v1/automations/12/' . $action);
})->with(['activate', 'pause', 'archive', 'duplicate']);

it('reports activating an empty automation as a validation error', function () {
    $http = MockHttpClient::make()->willRespond(422, [
        'message' => 'Add at least one step before activating.',
        'errors' => ['steps' => ['Add at least one step before activating.']],
    ]);

    expect(fn () => testClient($http)->automations->activate(12))
        ->toThrow(ValidationException::class, 'Add at least one step before activating.');
});

it('reports a free plan hitting a gated endpoint as a paid-plan error', function () {
    $http = MockHttpClient::make()->willRespond(403, [
        'message' => 'Automations require a paid plan.',
        'upgrade_url' => 'https://app.notifypress.com/billing',
    ]);

    $exception = null;

    try {
        testClient($http)->automations->create(AutomationPayload::onSubscriberCreated('X'));
    } catch (PaidPlanException $caught) {
        $exception = $caught;
    }

    // A null here means either nothing was thrown or it was the wrong type.
    expect($exception?->upgradeUrl)->toBe('https://app.notifypress.com/billing');
});

it('lists the templates gallery as raw config', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [['key' => 'welcome', 'name' => 'Welcome series', 'steps' => []]],
    ]);

    $templates = testClient($http)->automations->templates();

    expect($templates)->toHaveCount(1)
        ->and($templates->first())->toBe(['key' => 'welcome', 'name' => 'Welcome series', 'steps' => []]);
});

it('lists the reusable messages library', function () {
    $http = MockHttpClient::make()->willRespond(200, [
        'data' => [['id' => 3, 'title' => 'Day one', 'message' => 'Hello', 'purpose' => 'automation_message']],
        'automations' => [['id' => 1, 'name' => 'Welcome series', 'status' => 'active']],
    ]);

    $messages = testClient($http)->automations->messages(['sort' => 'newest']);

    expect($messages->first())->toBeInstanceOf(Notification::class)
        ->and($messages->first()?->purpose)->toBe('automation_message')
        ->and($messages->meta('automations'))->toHaveCount(1)
        ->and(queryOf($http->lastRequest()->uri))->toBe(['sort' => 'newest']);
});
