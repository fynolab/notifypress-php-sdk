<?php

declare(strict_types=1);

namespace NotifyPress\Query;

/**
 * Filters for GET /automations.
 *
 * This endpoint is offset-paginated rather than cursor-paginated, so it takes
 * a page number instead of a cursor.
 */
final class AutomationQuery implements Query
{
    /** @var array<string, scalar|null> */
    private array $params = [];

    public static function make(): self
    {
        return new self();
    }

    /** Page size, 1–100. The server defaults to 20. */
    public function perPage(int $perPage): self
    {
        $clone = clone $this;
        $clone->params['per_page'] = $perPage;

        return $clone;
    }

    public function page(int $page): self
    {
        $clone = clone $this;
        $clone->params['page'] = $page;

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
