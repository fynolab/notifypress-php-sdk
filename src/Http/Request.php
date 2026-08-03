<?php

declare(strict_types=1);

namespace NotifyPress\Http;

/**
 * A transport-neutral HTTP request.
 *
 * Deliberately not PSR-7: the SDK's own clients (cURL, streams, WordPress)
 * never need PSR-7, and requiring an implementation would force a dependency
 * on every consumer. PSR-7 is used only inside Psr18Client, where it is
 * unavoidable.
 */
final class Request
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $headers = [],
        public readonly ?string $body = null,
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly bool $verifySsl = true,
    ) {
    }
}
