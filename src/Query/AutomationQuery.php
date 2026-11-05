<?php

declare(strict_types=1);

namespace NotifyPress\Query;

use NotifyPress\Enum\AutomationStatus;

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
     * Only automations with this status. An unknown status is ignored by the API.
     */
    public function status(AutomationStatus|string $status): self
    {
        $clone = clone $this;
        $clone->params['status'] = $status instanceof AutomationStatus ? $status->value : $status;

        return $clone;
    }

    /**
     * Case-insensitive search on the automation name.
     */
    public function search(string $query): self
    {
        $clone = clone $this;

        if ($query === '') {
            unset($clone->params['q']);
        } else {
            $clone->params['q'] = $query;
        }

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
