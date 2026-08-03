<?php

declare(strict_types=1);

namespace NotifyPress\Model;

final class Subscriber extends Model
{
    /**
     * @param list<Tag> $tags
     */
    public function __construct(
        public readonly int $id,
        /** The FCM registration token. */
        public readonly string $token,
        public readonly ?int $siteId,
        public readonly ?int $userId,
        public readonly ?string $ip,
        public readonly ?string $city,
        public readonly ?string $country,
        public readonly ?string $browser,
        public readonly ?string $device,
        public readonly ?string $timezone,
        public readonly bool $active,
        /** ISO 8601. */
        public readonly ?string $createdAt,
        public readonly array $tags,
        public readonly bool $premiumUser,
        public readonly ?string $plan,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $tags = [];
        $rawTags = $data['tags'] ?? null;

        if (is_array($rawTags)) {
            foreach ($rawTags as $tag) {
                if (is_array($tag)) {
                    /** @var array<string, mixed> $tag */
                    $tags[] = Tag::fromArray($tag);
                }
            }
        }

        return new self(
            self::int($data, 'id') ?? 0,
            self::str($data, 'token') ?? '',
            self::int($data, 'site_id'),
            self::int($data, 'user_id'),
            self::str($data, 'ip'),
            self::str($data, 'city'),
            self::str($data, 'country'),
            self::str($data, 'browser'),
            self::str($data, 'device'),
            self::str($data, 'timezone'),
            self::bool($data, 'active'),
            self::str($data, 'created_at'),
            $tags,
            self::bool($data, 'premium_user'),
            self::str($data, 'plan'),
            $data,
        );
    }
}
