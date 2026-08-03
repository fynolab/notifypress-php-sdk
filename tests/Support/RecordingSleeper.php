<?php

declare(strict_types=1);

namespace NotifyPress\Tests\Support;

use NotifyPress\Http\Sleeper;

/**
 * Records backoff delays instead of waiting, so retry behaviour can be
 * asserted without making the suite slow.
 */
final class RecordingSleeper implements Sleeper
{
    /** @var list<float> */
    public array $slept = [];

    public function sleep(float $seconds): void
    {
        $this->slept[] = $seconds;
    }

    public function total(): float
    {
        return array_sum($this->slept);
    }
}
