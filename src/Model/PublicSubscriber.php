<?php

declare(strict_types=1);

namespace NotifyPress\Model;

/**
 * The trimmed subscriber record returned by the public endpoints.
 */
final class PublicSubscriber extends Model
{
    public function __construct(
        public readonly int $id,
        public readonly int $siteId,
        public readonly string $token,
        public readonly bool $active,
        public readonly bool $premiumUser,
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
            self::int($data, 'site_id') ?? 0,
            self::str($data, 'token') ?? '',
            self::bool($data, 'active'),
            self::bool($data, 'premium_user'),
            $data,
        );
    }
}
