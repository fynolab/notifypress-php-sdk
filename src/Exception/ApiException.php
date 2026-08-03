<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

use NotifyPress\Http\Response;

/**
 * The API returned a non-2xx status.
 */
class ApiException extends NotifyPressException
{
    /**
     * @param mixed $body The decoded JSON payload, or the raw string when the
     *                    response was not JSON.
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly mixed $body = null,
        public readonly ?Response $response = null,
    ) {
        parent::__construct($message);
    }
}
