<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

/**
 * DNS failure, TLS failure, connection reset — no HTTP response was received.
 */
final class ConnectionException extends TransportException
{
}
