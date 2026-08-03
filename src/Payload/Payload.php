<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

/**
 * A request body that knows how to serialise itself.
 *
 * Every SDK method that takes a payload also accepts a plain array, so the
 * fluent builders are a convenience rather than a requirement.
 */
interface Payload
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
