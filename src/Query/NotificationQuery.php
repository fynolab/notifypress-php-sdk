<?php

declare(strict_types=1);

namespace NotifyPress\Query;

use NotifyPress\Enum\NotificationStatus;
use NotifyPress\Enum\SortOrder;

/**
 * Filters for GET /notifications.
 *
 *     NotificationQuery::make()->status(NotificationStatus::SENT)->limit(50);
 */
final class NotificationQuery implements Query
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

    public function status(NotificationStatus|string $status): self
    {
        $clone = clone $this;
        $clone->params['status'] = $status instanceof NotificationStatus ? $status->value : $status;

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
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return $this->params;
    }
}
