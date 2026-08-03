<?php

declare(strict_types=1);

namespace NotifyPress\Model;

final class NotificationAnalytics extends Model
{
    /**
     * @param array<string, int> $browsers
     * @param array<string, int> $devices
     */
    public function __construct(
        public readonly int $views,
        public readonly int $clicks,
        /** Percentage, rounded to a whole number by the server. */
        public readonly int $clickThroughRate,
        public readonly array $browsers,
        public readonly array $devices,
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
            self::int($data, 'views') ?? 0,
            self::int($data, 'clicks') ?? 0,
            self::int($data, 'click_through_rate') ?? 0,
            self::counters(self::arr($data, 'browsers')),
            self::counters(self::arr($data, 'devices')),
            $data,
        );
    }

    public function chrome(): int
    {
        return $this->browsers['chrome'] ?? 0;
    }

    public function desktop(): int
    {
        return $this->devices['desktop'] ?? 0;
    }

    public function mobile(): int
    {
        return $this->devices['mobile'] ?? 0;
    }

    /**
     * @param  array<string, mixed> $values
     * @return array<string, int>
     */
    private static function counters(array $values): array
    {
        $counters = [];

        foreach ($values as $key => $value) {
            $counters[(string) $key] = is_numeric($value) ? (int) $value : 0;
        }

        return $counters;
    }
}
