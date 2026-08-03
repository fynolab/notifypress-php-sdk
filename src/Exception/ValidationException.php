<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

use NotifyPress\Http\Response;

/**
 * HTTP 422 — the request failed validation.
 */
final class ValidationException extends ApiException
{
    /**
     * @param array<string, list<string>> $errors Field name => messages.
     */
    public function __construct(
        string $message,
        int $status,
        mixed $body = null,
        ?Response $response = null,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $status, $body, $response);
    }

    /**
     * The first error message for a field, if that field failed.
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return array_keys($this->errors);
    }
}
