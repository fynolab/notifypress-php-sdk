<?php

declare(strict_types=1);

namespace NotifyPress\Query;

/**
 * Filters for GET /segments.
 *
 *     $client->segments->list(SegmentQuery::make()->perPage(100));
 */
final class SegmentQuery implements Query
{
    /** @var array<string, scalar|null> */
    private array $params = [];

    public static function make(): self
    {
        return new self();
    }

    /** Page size, 1–100. The server defaults to 10. */
    public function perPage(int $perPage): self
    {
        $clone = clone $this;
        $clone->params['per_page'] = $perPage;

        return $clone;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return $this->params;
    }
}
