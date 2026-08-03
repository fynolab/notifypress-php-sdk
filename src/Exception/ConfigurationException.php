<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

/**
 * The SDK was configured incorrectly: a missing API token, or no usable
 * HTTP client available in this environment.
 */
final class ConfigurationException extends NotifyPressException
{
}
