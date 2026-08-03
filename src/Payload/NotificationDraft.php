<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use NotifyPress\Enum\NotificationPurpose;
use NotifyPress\Enum\NotificationStatus;
use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Body for POST /notifications and PUT /notifications/{id}.
 *
 * Drafts allow a longer message than an immediate send (150 vs 100 characters).
 */
final class NotificationDraft implements Payload
{
    use BuildsPayload;

    public const MAX_TITLE_LENGTH = 50;

    public const MAX_MESSAGE_LENGTH = 150;

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

    public function status(NotificationStatus|string $status): static
    {
        return $this->set('status', $status instanceof NotificationStatus ? $status->value : $status);
    }

    public function purpose(NotificationPurpose|string $purpose): static
    {
        return $this->set('purpose', $purpose instanceof NotificationPurpose ? $purpose->value : $purpose);
    }

    /** Must be a site belonging to the authenticated account. */
    public function forSite(int $siteId): static
    {
        return $this->set('site_id', $siteId);
    }

    public function toSegment(int $segmentId): static
    {
        return $this->set('segment_id', $segmentId);
    }

    public function toEveryone(): static
    {
        return $this->set('segment_id', 'all');
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
}
