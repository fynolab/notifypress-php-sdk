<?php

declare(strict_types=1);

namespace NotifyPress\Http;

use NotifyPress\Exception\ErrorFactory;

/**
 * Decides whether a failed attempt should be retried, and how long to wait.
 *
 * Only idempotent requests are retried by default. A notification send that
 * times out or 502s may well have been processed, and re-sending would blast
 * a duplicate push to the entire audience — so mutations must opt in.
 */
final class RetryPolicy
{
    private const RETRYABLE_STATUSES = [429, 502, 503, 504];

    /** Never wait longer than this for a Retry-After, even if the server asks. */
    private const MAX_DELAY_SECONDS = 10.0;

    private const BASE_DELAY_SECONDS = 0.5;

    public function __construct(
        private readonly int $maxRetries = 2,
    ) {
    }

    public function shouldRetryStatus(int $status, int $attempt): bool
    {
        return $attempt < $this->maxRetries && in_array($status, self::RETRYABLE_STATUSES, true);
    }

    public function shouldRetryConnectionFailure(int $attempt): bool
    {
        return $attempt < $this->maxRetries;
    }

    /**
     * Honors Retry-After when the server sends one, otherwise falls back to
     * exponential backoff with jitter.
     */
    public function delayFor(int $attempt, ?Response $response = null): float
    {
        if ($response !== null) {
            $retryAfter = ErrorFactory::retryAfter($response);

            if ($retryAfter !== null) {
                return min((float) $retryAfter, self::MAX_DELAY_SECONDS);
            }
        }

        $base = self::BASE_DELAY_SECONDS * (2 ** $attempt);
        $jitter = $base * (random_int(0, 1000) / 1000);

        return min($base + $jitter, self::MAX_DELAY_SECONDS);
    }
}
