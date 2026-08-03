<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Body for POST /notifications/send.
 *
 *     SendNotification::make('Flash sale', '25% off, today only')
 *         ->targetUrl('https://example.com/sale')
 *         ->iconUrl('https://example.com/icon.png')
 *         ->toSegment(12);
 */
final class SendNotification implements Payload
{
    use BuildsPayload;

    /** Server-side limit on the title. */
    public const MAX_TITLE_LENGTH = 50;

    /** Server-side limit on the message for an immediate send. */
    public const MAX_MESSAGE_LENGTH = 100;

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

    public function title(string $title): static
    {
        return $this->set('title', $title);
    }

    public function message(string $message): static
    {
        return $this->set('message', $message);
    }

    /** URL opened when the notification is clicked. Max 255 characters. */
    public function targetUrl(string $url): static
    {
        return $this->set('target_url', $url);
    }

    /** Small icon shown in the notification. Max 255 characters. */
    public function iconUrl(string $url): static
    {
        return $this->set('icon_url', $url);
    }

    /** Large hero image. Max 255 characters. */
    public function imageUrl(string $url): static
    {
        return $this->set('image_url', $url);
    }

    /** Restricts delivery to one segment. */
    public function toSegment(int $segmentId): static
    {
        return $this->set('segment_id', $segmentId);
    }

    /** Sends to the site's entire audience (the server default). */
    public function toEveryone(): static
    {
        return $this->set('segment_id', 'all');
    }
}
