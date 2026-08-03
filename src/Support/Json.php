<?php

declare(strict_types=1);

namespace NotifyPress\Support;

use JsonException;

final class Json
{
    private function __construct()
    {
    }

    /**
     * Decodes a JSON string, returning null when the payload is empty or invalid.
     *
     * Invalid JSON is not an exception here: error responses from proxies are
     * often HTML, and the caller still needs the status code.
     */
    public static function decode(string $json): mixed
    {
        if (trim($json) === '') {
            return null;
        }

        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Narrows a decoded payload to an associative array, or an empty array.
     *
     * @return array<string, mixed>
     */
    public static function toArray(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public static function encode(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
