<?php

declare(strict_types=1);

namespace NotifyPress\Model;

/**
 * One of the last few sent notifications, as exposed to a site's visitors.
 */
final class RecentNotification extends Model
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $targetUrl,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::int($data, 'id') ?? 0,
            self::str($data, 'title') ?? '',
            self::str($data, 'target_url'),
            $data,
        );
    }
}
