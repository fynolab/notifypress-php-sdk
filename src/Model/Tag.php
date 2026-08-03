<?php

declare(strict_types=1);

namespace NotifyPress\Model;

use NotifyPress\Enum\TagType;

final class Tag extends Model
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $slug,
        /** Raw string rather than an enum — see typeEnum(). */
        public readonly ?string $type,
        public readonly ?int $subscribersCount,
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
            self::str($data, 'name') ?? '',
            self::str($data, 'slug'),
            self::str($data, 'type'),
            self::int($data, 'subscribers_count'),
            $data,
        );
    }

    public function typeEnum(): ?TagType
    {
        return $this->type === null ? null : TagType::tryFrom($this->type);
    }
}
