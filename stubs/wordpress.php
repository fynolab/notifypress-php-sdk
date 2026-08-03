<?php

declare(strict_types=1);

/**
 * Signatures for the handful of WordPress symbols WordPressClient uses.
 *
 * WordPress is not a Composer dependency of this SDK — it is the environment
 * the SDK may find itself running inside — so static analysis has no way to
 * resolve these on its own. This file is never loaded at runtime; it exists
 * only so `phpstan analyse` can check the WordPress code path properly instead
 * of having it excluded or ignored.
 *
 * @see https://developer.wordpress.org/reference/functions/wp_remote_request/
 */
class WP_Error
{
    /**
     * @param int|string $code
     */
    public function get_error_code(): int|string
    {
    }

    public function get_error_message(): string
    {
    }
}

/**
 * @param array<string, mixed> $args
 *
 * @return array<string, mixed>|WP_Error
 */
function wp_remote_request(string $url, array $args = []): array|WP_Error
{
}

/**
 * @phpstan-assert-if-true WP_Error $thing
 */
function is_wp_error(mixed $thing): bool
{
}

/**
 * @param array<string, mixed>|WP_Error $response
 */
function wp_remote_retrieve_response_code(array|WP_Error $response): int|string
{
}

/**
 * @param array<string, mixed>|WP_Error $response
 */
function wp_remote_retrieve_body(array|WP_Error $response): string
{
}

/**
 * Returns a case-insensitive dictionary object on modern WordPress, and a
 * plain array on older versions.
 *
 * @param array<string, mixed>|WP_Error $response
 *
 * @return array<string, mixed>|object
 */
function wp_remote_retrieve_headers(array|WP_Error $response): array|object
{
}
