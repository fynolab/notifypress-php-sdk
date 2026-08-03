<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

use DateTimeImmutable;
use NotifyPress\Http\Response;

/**
 * HTTP 429 — too many requests.
 */
final class RateLimitException extends ApiException
{
    public function __construct(
        string $message,
        int $status,
        mixed $body = null,
        ?Response $response = null,
        /** Seconds to wait, parsed from the Retry-After header when present. */
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status, $body, $response);
    }

    public function retryAt(): ?DateTimeImmutable
    {
        if ($this->retryAfter === null) {
            return null;
        }

        return new DateTimeImmutable('@' . (time() + $this->retryAfter));
    }
}
