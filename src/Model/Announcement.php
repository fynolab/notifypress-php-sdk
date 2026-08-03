<?php

declare(strict_types=1);

namespace NotifyPress\Model;

final class Announcement extends Model
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $linkUrl,
        public readonly ?string $linkLabel,
        public readonly ?string $createdAt,
        public readonly bool $read,
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
            self::str($data, 'body') ?? '',
            self::str($data, 'link_url'),
            self::str($data, 'link_label'),
            self::str($data, 'created_at'),
            self::bool($data, 'read'),
            $data,
        );
    }
}
