<?php

declare(strict_types=1);

namespace NotifyPress\Pagination;

use ArrayIterator;
use Countable;
use Generator;
use IteratorAggregate;
use Traversable;

/**
 * One page of a Laravel cursor-paginated endpoint.
 *
 * Endpoints built straight from `cursorPaginate()->toArray()` put the cursors
 * at the top level; resource-collection endpoints (`GET /sites`) nest them
 * under `meta`. Both shapes are read here so callers never have to care.
 *
 * Iterating a page yields **that page only**. Walking the whole set is an
 * explicit choice, because each step is another HTTP round trip:
 *
 *     foreach ($client->subscribers->list() as $subscriber) { }              // page 1
 *     foreach ($client->subscribers->list()->autoPaging() as $subscriber) { } // everything
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final class CursorPage implements Countable, IteratorAggregate
{
    /** @var list<T> */
    public readonly array $data;

    public readonly ?string $nextCursor;

    public readonly ?string $prevCursor;

    public readonly ?int $perPage;

    /** Cached count of the full set. Present on GET /subscribers only. */
    public readonly ?int $total;

    /** @var array<string, mixed> The undecorated page payload. */
    public readonly array $raw;

    /** @var callable(array<string, mixed>): T */
    private $hydrator;

    /** @var callable(string): array<string, mixed> */
    private $fetchRaw;

    /**
     * @param array<string, mixed>                   $raw
     * @param callable(array<string, mixed>): T      $hydrator
     * @param callable(string): array<string, mixed> $fetchRaw
     */
    public function __construct(array $raw, callable $hydrator, callable $fetchRaw)
    {
        $this->raw = $raw;
        $this->hydrator = $hydrator;
        $this->fetchRaw = $fetchRaw;

        $rows = isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : [];
        $data = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $data[] = $hydrator($row);
            }
        }

        $this->data = $data;

        $meta = isset($raw['meta']) && is_array($raw['meta']) ? $raw['meta'] : [];

        $this->nextCursor = self::readString($raw, $meta, 'next_cursor');
        $this->prevCursor = self::readString($raw, $meta, 'prev_cursor');
        $this->perPage = self::readInt($raw, $meta, 'per_page');
        $this->total = self::readInt($raw, $meta, 'total');
    }

    public function hasNextPage(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * @return self<T>|null
     */
    public function nextPage(): ?self
    {
        if ($this->nextCursor === null) {
            return null;
        }

        return new self(($this->fetchRaw)($this->nextCursor), $this->hydrator, $this->fetchRaw);
    }

    /**
     * Yields every item across every remaining page, fetching each page only
     * when the previous one is exhausted.
     *
     * @return Generator<int, T>
     */
    public function autoPaging(): Generator
    {
        $page = $this;

        while ($page !== null) {
            foreach ($page->data as $item) {
                yield $item;
            }

            $page = $page->nextPage();
        }
    }

    /**
     * Every item across every remaining page, as one array. Only safe on sets
     * small enough to hold in memory — prefer autoPaging() otherwise.
     *
     * @return list<T>
     */
    public function all(): array
    {
        return iterator_to_array($this->autoPaging(), false);
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

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $meta
     */
    private static function readString(array $raw, array $meta, string $key): ?string
    {
        $value = $raw[$key] ?? $meta[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $meta
     */
    private static function readInt(array $raw, array $meta, string $key): ?int
    {
        $value = $raw[$key] ?? $meta[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
