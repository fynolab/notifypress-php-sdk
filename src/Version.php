<?php

declare(strict_types=1);

namespace NotifyPress;

final class Version
{
    public const VERSION = '0.2.0';

    private function __construct()
    {
    }

    public static function userAgent(): string
    {
        return 'notifypress-php/' . self::VERSION . ' php/' . PHP_VERSION;
    }
}
