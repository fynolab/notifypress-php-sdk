<?php

declare(strict_types=1);

namespace NotifyPress\Model;

use NotifyPress\Enum\NotificationStatus;

final class Notification extends Model
{
    public function __construct(
        public readonly int $id,
        /** List endpoints truncate this to 20 characters; retrieve() returns it in full. */
        public readonly string $title,
        /** List endpoints truncate this to 50 characters; retrieve() returns it in full. */
        public readonly string $message,
        /** Raw string rather than an enum — see statusEnum(). */
        public readonly ?string $status,
        public readonly ?string $purpose,
        public readonly ?string $createdBy,
        public readonly ?string $targetUrl,
        public readonly ?string $iconUrl,
        public readonly ?string $imageUrl,
        public readonly ?int $segmentId,
        /**
         * "Y-m-d H:i:s" in the account's display timezone — not ISO 8601 and
         * not UTC. Kept as a string so no timezone is silently assumed.
         */
        public readonly ?string $scheduledAt,
        public readonly ?string $nextRunAt,
        public readonly ?string $sentAt,
        public readonly ?string $createdAt,
        public readonly ?NotificationAnalytics $analytics,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $analytics = self::arr($data, 'analytics');

        return new self(
            self::int($data, 'id') ?? 0,
            self::str($data, 'title') ?? '',
            self::str($data, 'message') ?? '',
            self::str($data, 'status'),
            self::str($data, 'purpose'),
            self::str($data, 'created_by'),
            self::str($data, 'target_url'),
            self::str($data, 'icon_url'),
            self::str($data, 'image_url'),
            self::int($data, 'segment_id'),
            self::str($data, 'scheduled_at'),
            self::str($data, 'next_run_at'),
            self::str($data, 'sent_at'),
            self::str($data, 'created_at'),
            $analytics === [] ? null : NotificationAnalytics::fromArray($analytics),
            $data,
        );
    }

    /**
     * Null when the API returns a status this SDK release predates.
     */
    public function statusEnum(): ?NotificationStatus
    {
        return $this->status === null ? null : NotificationStatus::tryFrom($this->status);
    }

    public function isSent(): bool
    {
        return $this->status === NotificationStatus::SENT->value;
    }

    public function isScheduled(): bool
    {
        return $this->status === NotificationStatus::SCHEDULED->value;
    }
}
