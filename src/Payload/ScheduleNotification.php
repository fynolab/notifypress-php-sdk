<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use DateTimeInterface;
use InvalidArgumentException;
use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Body for POST /notifications/schedule.
 *
 *     ScheduleNotification::make('Good morning', 'Rise and shine')
 *         ->at(new DateTimeImmutable('2026-08-15 09:30:00', new DateTimeZone('Asia/Dhaka')));
 */
final class ScheduleNotification implements Payload
{
    use BuildsPayload;

    /**
     * The format the API expects for scheduled_at: ISO 8601 with the offset, so the value names
     * one exact instant (e.g. "2026-08-15T09:30:00+06:00"). The API stores it in UTC.
     */
    public const DATE_FORMAT = DateTimeInterface::ATOM;

    /**
     * An ISO 8601 date-time that carries its offset ("...Z" or "...+06:00").
     */
    private const INSTANT_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/';

    public function __construct(string $title, string $message)
    {
        $this->attributes = [
            'title' => $title,
            'message' => $message,
        ];
    }

    public static function make(string $title, string $message): self
    {
        return new self($title, $message);
    }

    /**
     * Sets the send time from a DateTime. Its own timezone decides the instant: 09:30 in
     * Asia/Dhaka and 09:30 in America/Toronto are different moments, and each is sent with its
     * offset. Build it in your user's timezone.
     */
    public function at(DateTimeInterface $when): static
    {
        return $this->set('scheduled_at', $when->format(self::DATE_FORMAT));
    }

    /**
     * Sets the send time from an ISO 8601 string that carries its offset, e.g.
     * "2026-08-15T09:30:00+06:00" or "2026-08-15T03:30:00Z".
     *
     * @throws InvalidArgumentException When the value has no offset (the API rejects it).
     */
    public function scheduledAt(string $scheduledAt): static
    {
        return $this->set('scheduled_at', self::assertInstant($scheduledAt));
    }

    /**
     * Checks that $value names one exact instant (ISO 8601 with an offset). A bare
     * "2026-08-15 09:30:00" is ambiguous, so the API rejects it; fail here instead.
     *
     * @throws InvalidArgumentException
     */
    public static function assertInstant(string $value): string
    {
        if (preg_match(self::INSTANT_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'scheduled_at must be ISO 8601 with a timezone offset, like "2026-08-15T09:30:00+06:00"; got "%s".',
                $value
            ));
        }

        return $value;
    }

    public function targetUrl(string $url): static
    {
        return $this->set('target_url', $url);
    }

    public function iconUrl(string $url): static
    {
        return $this->set('icon_url', $url);
    }

    public function imageUrl(string $url): static
    {
        return $this->set('image_url', $url);
    }

    public function toSegment(int $segmentId): static
    {
        return $this->set('segment_id', $segmentId);
    }

    public function toEveryone(): static
    {
        return $this->set('segment_id', 'all');
    }
}
