<?php

declare(strict_types=1);

namespace NotifyPress\Model;

/**
 * Base for every response object.
 *
 * The full decoded payload is always kept in $raw. That is what makes the SDK
 * forward-compatible: when the API grows a field, an older SDK still exposes
 * it through get() instead of silently discarding it.
 */
abstract class Model
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(public readonly array $raw = [])
    {
    }

    /**
     * Reads a value from the raw payload using dot notation.
     *
     *     $notification->get('analytics.browsers.chrome');
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->raw;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @param  array<string, mixed> $data
     */
    protected static function str(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return $value === null ? $default : (bool) $value;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected static function arr(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
