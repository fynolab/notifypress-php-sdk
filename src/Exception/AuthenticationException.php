<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

/**
 * HTTP 401, or a 403 raised by token verification (an {"error": "..."} body).
 *
 * Typically a missing, malformed, revoked, or regenerated API token, or a
 * suspended account.
 */
final class AuthenticationException extends ApiException
{
}
