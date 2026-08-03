<?php

declare(strict_types=1);

namespace NotifyPress\Model;

final class Segment extends Model
{
    /**
     * @param array<string, mixed> $rule Groups keyed location/device/browser,
     *                                   each {condition, values}.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $isActive,
        public readonly array $rule,
        /** Formatted "d M Y" by the server, e.g. "03 Aug 2026". */
        public readonly ?string $createdAt,
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
            self::str($data, 'description'),
            self::bool($data, 'is_active'),
            self::arr($data, 'rule'),
            self::str($data, 'created_at'),
            $data,
        );
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
