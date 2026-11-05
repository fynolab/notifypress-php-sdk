<?php

declare(strict_types=1);

namespace NotifyPress\Model;

final class Segment extends Model
{
    /**
     * @param array<string, mixed> $rule Groups keyed location/device/browser/tags,
     *                                   each {condition, values}.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $isActive,
        public readonly array $rule,
        /** ISO 8601 in UTC, e.g. "2026-08-03T14:00:00+00:00". */
        public readonly ?string $createdAt,
        array $raw = [],
        /** Stored audience size on list rows; may be up to 30 minutes old. */
        public readonly ?int $subscriberCount = null,
        /** When subscriberCount was computed (ISO 8601), or null if never. */
        public readonly ?string $subscriberCountComputedAt = null,
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
            self::str($data, 'description'),
            self::bool($data, 'is_active'),
            self::arr($data, 'rule'),
            self::str($data, 'created_at'),
            $data,
            self::int($data, 'subscriber_count'),
            self::str($data, 'subscriber_count_computed_at'),
        );
    }

    /**
     * Tag IDs the segment targets (the rule's `tags` group), as strings like the API returns.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->values('tags');
    }

    /**
     * @return list<string>
     */
    public function countries(): array
    {
        return $this->values('location');
    }

    /**
     * @return list<string>
     */
    public function devices(): array
    {
        return $this->values('device');
    }

    /**
     * @return list<string>
     */
    public function browsers(): array
    {
        return $this->values('browser');
    }

    /**
     * @return list<string>
     */
    private function values(string $group): array
    {
        $values = $this->rule[$group]['values'] ?? [];

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, 'is_string'));
    }
}
