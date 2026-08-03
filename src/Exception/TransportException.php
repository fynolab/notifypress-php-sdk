<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

/**
 * The request never produced an HTTP response.
 */
abstract class TransportException extends NotifyPressException
{
}
