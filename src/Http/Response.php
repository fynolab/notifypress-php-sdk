<?php

declare(strict_types=1);

namespace NotifyPress\Http;

use NotifyPress\Exception\NotifyPressException;

final class Response
{
    /** @var array<string, string> Header names lowercased. */
    public readonly array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        array $headers = [],
        public readonly string $body = '',
    ) {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }

        $this->headers = $normalized;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower($this->header('content-type') ?? ''), 'application/json');
    }

    /**
     * Writes the response body to disk. Used for binary payloads such as
     * generated PDF reports.
     *
     * @throws NotifyPressException when the file cannot be written
     */
    public function saveTo(string $path): int
    {
        $written = @file_put_contents($path, $this->body);

        if ($written === false) {
            throw new NotifyPressException(sprintf('Could not write the response body to "%s".', $path));
        }

        return $written;
    }
}
