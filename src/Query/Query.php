<?php

declare(strict_types=1);

namespace NotifyPress\Query;

/**
 * A set of list-endpoint query parameters.
 *
 * Like payloads, every list method also accepts a plain array, so these
 * builders are a convenience.
 */
interface Query
{
    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array;
}
