<?php

declare(strict_types=1);

namespace NotifyPress;

use NotifyPress\Http\HttpClientInterface;

/**
 * Immutable client configuration.
 *
 * Every with*() method returns a new instance, so a Config can be shared and
 * specialised without surprising anyone who still holds the original:
 *
 *     $config = Config::make('1|secret')
 *         ->withTimeout(10.0)
 *         ->withMaxRetries(4);
 */
final class Config
{
    public const DEFAULT_BASE_URL = 'https://app.notifypress.com/api/v1';

    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(
        public readonly string $apiToken = '',
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly ?HttpClientInterface $httpClient = null,
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly int $maxRetries = 2,
        public readonly array $defaultHeaders = [],
        public readonly bool $verifySsl = true,
    ) {
    }

    public static function make(string $apiToken = ''): self
    {
        return new self($apiToken);
    }

    public function withApiToken(string $apiToken): self
    {
        return $this->copy(apiToken: $apiToken);
    }

    public function withBaseUrl(string $baseUrl): self
    {
        return $this->copy(baseUrl: $baseUrl);
    }

    public function withHttpClient(HttpClientInterface $httpClient): self
    {
        return $this->copy(httpClient: $httpClient);
    }

    public function withTimeout(float $seconds): self
    {
        return $this->copy(timeout: $seconds);
    }

    public function withConnectTimeout(float $seconds): self
    {
        return $this->copy(connectTimeout: $seconds);
    }

    public function withMaxRetries(int $maxRetries): self
    {
        return $this->copy(maxRetries: $maxRetries);
    }

    /**
     * @param array<string, string> $headers
     */
    public function withDefaultHeaders(array $headers): self
    {
        return $this->copy(defaultHeaders: $headers);
    }

    public function withHeader(string $name, string $value): self
    {
        return $this->copy(defaultHeaders: array_merge($this->defaultHeaders, [$name => $value]));
    }

    /**
     * Disables TLS certificate verification. Intended for local development
     * against a self-signed certificate — never enable it in production.
     */
    public function withoutSslVerification(): self
    {
        return $this->copy(verifySsl: false);
    }

    /**
     * @param array<string, string>|null $defaultHeaders
     */
    private function copy(
        ?string $apiToken = null,
        ?string $baseUrl = null,
        ?HttpClientInterface $httpClient = null,
        ?float $timeout = null,
        ?float $connectTimeout = null,
        ?int $maxRetries = null,
        ?array $defaultHeaders = null,
        ?bool $verifySsl = null,
    ): self {
        return new self(
            $apiToken ?? $this->apiToken,
            $baseUrl ?? $this->baseUrl,
            $httpClient ?? $this->httpClient,
            $timeout ?? $this->timeout,
            $connectTimeout ?? $this->connectTimeout,
            $maxRetries ?? $this->maxRetries,
            $defaultHeaders ?? $this->defaultHeaders,
            $verifySsl ?? $this->verifySsl,
        );
    }
}
