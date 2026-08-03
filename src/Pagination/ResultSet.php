<?php

declare(strict_types=1);

namespace NotifyPress\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An unpaginated `{"data": [...]}` response.
 *
 * Several endpoints return the whole collection alongside extra envelope keys
 * — `isPaidUser` on segments, `unread_count` on announcements, `automations`
 * on the messages library. Returning a bare array would throw those away, so
 * the collection is iterable and countable while meta() keeps the rest.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final class ResultSet implements Countable, IteratorAggregate
{
    /** @var list<T> */
    public readonly array $data;

    /** @var array<string, mixed> The undecorated payload. */
    public readonly array $raw;

    /**
     * @param array<string, mixed>              $raw
     * @param callable(array<string, mixed>): T $hydrator
     */
    public function __construct(array $raw, callable $hydrator)
    {
        $this->raw = $raw;

        $rows = isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : [];
        $data = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $data[] = $hydrator($row);
            }
        }

        $this->data = $data;
    }

    /**
     * A key from the envelope surrounding `data`.
     */
    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->raw[$key] ?? $default;
    }

    public function isPaidUser(): bool
    {
        return (bool) ($this->raw['isPaidUser'] ?? false);
    }

    /**
     * @return list<T>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->data[0] ?? null;
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function isEmpty(): bool
    {
        return $this->data === [];
    }
}
