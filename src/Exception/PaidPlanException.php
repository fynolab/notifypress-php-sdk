<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

use NotifyPress\Http\Response;

/**
 * HTTP 403 for an endpoint that requires a paid NotifyPress subscription.
 */
final class PaidPlanException extends ApiException
{
    public function __construct(
        string $message,
        int $status,
        mixed $body = null,
        ?Response $response = null,
        public readonly ?string $upgradeUrl = null,
    ) {
        parent::__construct($message, $status, $body, $response);
    }
}
