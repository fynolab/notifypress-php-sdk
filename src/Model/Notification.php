<?php

declare(strict_types=1);

namespace NotifyPress\Model;

use DateTimeImmutable;
use NotifyPress\Enum\NotificationStatus;
use Throwable;

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
         * ISO 8601 in UTC, like every timestamp on responses (e.g.
         * "2026-08-15T03:30:00+00:00"). See scheduledAtDate() for a DateTime.
         */
        public readonly ?string $scheduledAt,
        public readonly ?string $nextRunAt,
        public readonly ?string $sentAt,
        public readonly ?string $createdAt,
        public readonly ?NotificationAnalytics $analytics,
        array $raw = [],
        /** IANA timezone a recurring send's time and weekdays are in; null when not recurring. */
        public readonly ?string $recurringTimezone = null,
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
            self::str($data, 'recurring_timezone'),
        );
    }

    public function scheduledAtDate(): ?DateTimeImmutable
    {
        return self::instant($this->scheduledAt);
    }

    public function nextRunAtDate(): ?DateTimeImmutable
    {
        return self::instant($this->nextRunAt);
    }

    public function sentAtDate(): ?DateTimeImmutable
    {
        return self::instant($this->sentAt);
    }

    public function createdAtDate(): ?DateTimeImmutable
    {
        return self::instant($this->createdAt);
    }

    /**
     * A response timestamp (ISO 8601 with an offset) as a DateTime; null when absent or invalid.
     * Convert it with ->setTimezone() to show it in your user's timezone.
     */
    private static function instant(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
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
