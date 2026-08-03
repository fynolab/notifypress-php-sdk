<?php

declare(strict_types=1);

namespace NotifyPress\Http;

/**
 * Strips credentials out of anything that might be logged or rendered.
 *
 * The API token grants full access to a site, so it must never reach a log
 * file, an exception message, or a stack trace. Everything this SDK exposes
 * for diagnostics goes through here first.
 */
final class Redactor
{
    private const REDACTED = '***';

    /** Header names whose entire value is a credential. */
    private const SECRET_HEADERS = [
        'authorization',
        'notifypress-subscription-token',
        'x-api-key',
        'cookie',
        'set-cookie',
    ];

    private function __construct()
    {
    }

    /**
     * @param  array<string, string> $headers
     * @return array<string, string>
     */
    public static function headers(array $headers): array
    {
        $safe = [];

        foreach ($headers as $name => $value) {
            $lower = strtolower($name);

            if (in_array($lower, self::SECRET_HEADERS, true)) {
                // Keep the scheme so "is it a Bearer token?" stays debuggable.
                $safe[$name] = str_starts_with(strtolower($value), 'bearer ')
                    ? 'Bearer ' . self::REDACTED
                    : self::REDACTED;

                continue;
            }

            if (str_contains($lower, 'token') || str_contains($lower, 'secret') || str_contains($lower, 'key')) {
                $safe[$name] = self::REDACTED;

                continue;
            }

            $safe[$name] = $value;
        }

        return $safe;
    }

    /**
     * Removes a known secret from arbitrary text.
     */
    public static function text(string $text, ?string $secret): string
    {
        if ($secret === null || strlen($secret) < 8) {
            return $text;
        }

        return str_replace($secret, self::REDACTED, $text);
    }
}
