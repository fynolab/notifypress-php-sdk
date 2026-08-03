<?php

declare(strict_types=1);

namespace NotifyPress\Http;

final class Query
{
    private function __construct()
    {
    }

    /**
     * Builds a query string from a params array.
     *
     * Entries whose value is null or an empty string are skipped, and booleans
     * are sent as 1/0 — matching what the API's existing consumers send.
     * Returns either an empty string or a string starting with "?".
     *
     * @param array<string, scalar|null> $query
     */
    public static function toString(array $query): string
    {
        $pairs = [];

        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $pairs[$key] = (string) $value;
        }

        if ($pairs === []) {
            return '';
        }

        return '?' . http_build_query($pairs, '', '&', PHP_QUERY_RFC3986);
    }
}
