<?php

declare(strict_types=1);

namespace NotifyPress\Query;

use NotifyPress\Enum\SortOrder;

/**
 * Filters for GET /subscribers.
 */
final class SubscriberQuery implements Query
{
    /** @var array<string, scalar|null> */
    private array $params = [];

    public static function make(): self
    {
        return new self();
    }

    /** Page size, 1–100. The server defaults to 10. */
    public function limit(int $limit): self
    {
        $clone = clone $this;
        $clone->params['limit'] = $limit;

        return $clone;
    }

    /** Defaults to the site linked to the API token. */
    public function forSite(int $siteId): self
    {
        $clone = clone $this;
        $clone->params['site_id'] = $siteId;

        return $clone;
    }

    public function order(SortOrder|string $order): self
    {
        $clone = clone $this;
        $clone->params['order'] = $order instanceof SortOrder ? $order->value : $order;

        return $clone;
    }

    public function oldestFirst(): self
    {
        return $this->order(SortOrder::ASC);
    }

    public function newestFirst(): self
    {
        return $this->order(SortOrder::DESC);
    }

    /**
     * Order by registration date (then id), the same order as the dashboard's Subscribers page.
     * It stays fast on large sites; the default id order has to sort the whole site first.
     * Combine with oldestFirst() / newestFirst(). Kept on every page by nextPage().
     */
    public function sortByCreatedAt(): self
    {
        $clone = clone $this;
        $clone->params['sort'] = 'created_at';

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
