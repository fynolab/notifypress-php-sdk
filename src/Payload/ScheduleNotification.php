<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use DateTimeInterface;
use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Body for POST /notifications/schedule.
 *
 *     ScheduleNotification::make('Good morning', 'Rise and shine')
 *         ->at(new DateTimeImmutable('2026-08-15 09:30:00'));
 */
final class ScheduleNotification implements Payload
{
    use BuildsPayload;

    /**
     * The format the API expects for scheduled_at.
     *
     * Note this is NOT ISO 8601 and NOT UTC — see at().
     */
    public const DATE_FORMAT = 'Y-m-d H:i:s';

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
     * Sets the send time from a DateTime.
     *
     * IMPORTANT: the API interprets this value in the NotifyPress account's
     * *display timezone*, not UTC and not the timezone carried by $when. The
     * SDK formats the wall-clock time exactly as given and performs no
     * conversion, so pass a DateTime already expressed in the account's
     * timezone or the notification will go out at the wrong moment.
     */
    public function at(DateTimeInterface $when): static
    {
        return $this->set('scheduled_at', $when->format(self::DATE_FORMAT));
    }

    /**
     * Sets the send time from a pre-formatted "Y-m-d H:i:s" string.
     */
    public function scheduledAt(string $scheduledAt): static
    {
        return $this->set('scheduled_at', $scheduledAt);
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
