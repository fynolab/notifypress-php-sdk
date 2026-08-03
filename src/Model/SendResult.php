<?php

declare(strict_types=1);

namespace NotifyPress\Model;

/**
 * Result of a send, schedule, or recurring-send call.
 */
final class SendResult extends Model
{
    public function __construct(
        public readonly string $message,
        public readonly ?Notification $notification,
        /** How many subscribers the send was queued for. */
        public readonly ?int $subscriberTotal,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $notification = self::arr($data, 'notification');

        return new self(
            self::str($data, 'message') ?? '',
            $notification === [] ? null : Notification::fromArray($notification),
            self::int($data, 'subscriber_total'),
            $data,
        );
    }
}
