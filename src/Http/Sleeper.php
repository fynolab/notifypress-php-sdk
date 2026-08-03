<?php

declare(strict_types=1);

namespace NotifyPress\Http;

/**
 * Indirection over sleeping so retry behaviour is testable without
 * actually waiting.
 */
interface Sleeper
{
    public function sleep(float $seconds): void;
}
