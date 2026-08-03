<?php

declare(strict_types=1);

namespace NotifyPress\Http;

/**
 * Per-call overrides, accepted as the trailing argument of every SDK method.
 *
 *     $client->notifications->list([], RequestOptions::make()->timeout(5.0));
 */
final class RequestOptions
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly array $headers = [],
        public readonly ?float $timeout = null,
        /** Force retry behaviour for a request the SDK would not retry by default. */
        public readonly ?bool $retryable = null,
        /** Return the raw Response instead of the decoded body. */
        public readonly bool $raw = false,
    ) {
    }

    public static function make(): self
    {
        return new self();
    }

    public function withHeader(string $name, string $value): self
    {
        return new self(
            array_merge($this->headers, [$name => $value]),
            $this->timeout,
            $this->retryable,
            $this->raw
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            array_merge($this->headers, $headers),
            $this->timeout,
            $this->retryable,
            $this->raw
        );
    }

    public function timeout(float $seconds): self
    {
        return new self($this->headers, $seconds, $this->retryable, $this->raw);
    }

    public function retryable(bool $retryable = true): self
    {
        return new self($this->headers, $this->timeout, $retryable, $this->raw);
    }

    public function raw(bool $raw = true): self
    {
        return new self($this->headers, $this->timeout, $this->retryable, $raw);
    }
}
