<?php

declare(strict_types=1);

namespace NotifyPress\Pagination;

use ArrayIterator;
use Countable;
use Generator;
use IteratorAggregate;
use Traversable;

/**
 * One page of a Laravel offset-paginated endpoint (`paginate()`).
 *
 * Only GET /automations uses this shape. It gets its own type rather than
 * being squeezed into CursorPage, because page numbers and cursors are not
 * interchangeable and pretending otherwise would produce a confusing API.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final class OffsetPage implements Countable, IteratorAggregate
{
    /** @var list<T> */
    public readonly array $data;

    public readonly int $currentPage;

    public readonly int $lastPage;

    public readonly int $perPage;

    public readonly int $total;

    /** @var array<string, mixed> The undecorated page payload. */
    public readonly array $raw;

    /** @var callable(array<string, mixed>): T */
    private $hydrator;

    /** @var callable(int): array<string, mixed> */
    private $fetchRaw;

    /**
     * @param array<string, mixed>                $raw
     * @param callable(array<string, mixed>): T   $hydrator
     * @param callable(int): array<string, mixed> $fetchRaw
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
        $this->currentPage = self::readInt($raw, 'current_page', 1);
        $this->lastPage = self::readInt($raw, 'last_page', 1);
        $this->perPage = self::readInt($raw, 'per_page', count($data));
        $this->total = self::readInt($raw, 'total', count($data));
    }

    /**
     * Whether the account has a paid subscription. This endpoint reports it so
     * a client can render a locked state instead of guessing from a 403.
     */
    public function isPaidUser(): bool
    {
        return (bool) ($this->raw['isPaidUser'] ?? false);
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * @return self<T>|null
     */
    public function nextPage(): ?self
    {
        if (! $this->hasNextPage()) {
            return null;
        }

        return new self(($this->fetchRaw)($this->currentPage + 1), $this->hydrator, $this->fetchRaw);
    }

    /**
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
     */
    private static function readInt(array $raw, string $key, int $default): int
    {
        $value = $raw[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
