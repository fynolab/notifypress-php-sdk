<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

/**
 * The configured timeout elapsed before a response arrived.
 *
 * Never retried automatically: the server may have processed the request
 * even though the response did not arrive in time.
 */
final class TimeoutException extends TransportException
{
}
