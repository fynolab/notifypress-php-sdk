<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

/**
 * HTTP 403 that is neither an auth failure nor a paid-plan gate: resource
 * ownership, plan resource caps, or an origin mismatch on a public endpoint.
 */
final class ForbiddenException extends ApiException
{
}
