<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use NotifyPress\Enum\AutomationStepType;
use NotifyPress\Enum\AutomationTrigger;
use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Body for POST /automations and PUT /automations/{id}.
 *
 *     AutomationPayload::onSubscriberCreated('Welcome series')
 *         ->wait(60)
 *         ->sendNotification(42)
 *         ->addTag(7);
 */
final class AutomationPayload implements Payload
{
    use BuildsPayload;

    private function __construct(string $name, AutomationTrigger $trigger)
    {
        $this->attributes = [
            'name' => $name,
            'trigger_type' => $trigger->value,
            'steps' => [],
        ];
    }

    /** Enrols every new subscriber on the site. */
    public static function onSubscriberCreated(string $name): self
    {
        return new self($name, AutomationTrigger::SUBSCRIBER_CREATED);
    }

    /** Enrols subscribers when the named event is triggered via the API. */
    public static function onCustomEvent(string $name, string $event): self
    {
        $automation = new self($name, AutomationTrigger::CUSTOM_EVENT);

        return $automation->set('trigger_config', ['event' => $event]);
    }

    public function description(string $description): static
    {
        return $this->set('description', $description);
    }

    /** Pauses the subscriber for the given number of minutes. */
    public function wait(int $minutes): static
    {
        return $this->addStep(AutomationStepType::WAIT, ['minutes' => $minutes]);
    }

    /** Sends an existing notification to the enrolled subscriber. */
    public function sendNotification(int $notificationId): static
    {
        return $this->addStep(AutomationStepType::SEND_NOTIFICATION, ['notification_id' => $notificationId]);
    }

    public function addTag(int $tagId): static
    {
        return $this->addStep(AutomationStepType::ADD_TAG, ['tag_id' => $tagId]);
    }

    /** Posts the subscriber payload to an HTTPS URL. */
    public function webhook(string $url): static
    {
        return $this->addStep(AutomationStepType::WEBHOOK, ['url' => $url]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function addStep(AutomationStepType $type, array $config = []): static
    {
        $steps = $this->attributes['steps'] ?? [];
        $steps = is_array($steps) ? $steps : [];
        $steps[] = ['type' => $type->value, 'config' => $config];

        return $this->set('steps', $steps);
    }
}
