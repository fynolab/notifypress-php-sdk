<?php

declare(strict_types=1);

namespace NotifyPress\Resource;

use NotifyPress\Http\Multipart\MultipartBody;
use NotifyPress\Http\RequestOptions;
use NotifyPress\Http\Transport;
use NotifyPress\Pagination\CursorPage;
use NotifyPress\Pagination\OffsetPage;
use NotifyPress\Pagination\ResultSet;
use NotifyPress\Payload\Payload;
use NotifyPress\Query\Query;

abstract class Resource
{
    public function __construct(protected readonly Transport $transport)
    {
    }

    /**
     * @template T
     *
     * @param  array<string, scalar|null>        $query
     * @param  callable(array<string, mixed>): T $hydrator
     * @return CursorPage<T>
     */
    protected function cursorPage(
        string $path,
        array $query,
        callable $hydrator,
        ?RequestOptions $options = null,
    ): CursorPage {
        $fetchRaw = function (string $cursor) use ($path, $query, $options): array {
            return $this->transport->request(
                'GET',
                $path,
                array_merge($query, ['cursor' => $cursor]),
                null,
                $options
            );
        };

        return new CursorPage(
            $this->transport->request('GET', $path, $query, null, $options),
            $hydrator,
            $fetchRaw
        );
    }

    /**
     * @template T
     *
     * @param  array<string, scalar|null>        $query
     * @param  callable(array<string, mixed>): T $hydrator
     * @return OffsetPage<T>
     */
    protected function offsetPage(
        string $path,
        array $query,
        callable $hydrator,
        ?RequestOptions $options = null,
    ): OffsetPage {
        $fetchRaw = function (int $page) use ($path, $query, $options): array {
            return $this->transport->request(
                'GET',
                $path,
                array_merge($query, ['page' => $page]),
                null,
                $options
            );
        };

        return new OffsetPage(
            $this->transport->request('GET', $path, $query, null, $options),
            $hydrator,
            $fetchRaw
        );
    }

    /**
     * @template T
     *
     * @param  array<string, scalar|null>        $query
     * @param  callable(array<string, mixed>): T $hydrator
     * @return ResultSet<T>
     */
    protected function resultSet(
        string $path,
        array $query,
        callable $hydrator,
        ?RequestOptions $options = null,
    ): ResultSet {
        return new ResultSet($this->transport->request('GET', $path, $query, null, $options), $hydrator);
    }

    /**
     * Unwraps a `{"data": {...}}` envelope and hydrates it.
     *
     * @template T
     *
     * @param  array<string, mixed>              $response
     * @param  callable(array<string, mixed>): T $hydrator
     * @return T
     */
    protected function hydrateEnvelope(array $response, callable $hydrator): mixed
    {
        $data = $response['data'] ?? null;

        /** @var array<string, mixed> $data */
        $data = is_array($data) ? $data : $response;

        return $hydrator($data);
    }

    /**
     * @param  Payload|array<string, mixed>|MultipartBody|null $payload
     * @return array<string, mixed>|MultipartBody|null
     */
    protected function body(Payload|array|MultipartBody|null $payload): array|MultipartBody|null
    {
        if ($payload instanceof MultipartBody || $payload === null) {
            return $payload;
        }

        return $payload instanceof Payload ? $payload->toArray() : $payload;
    }

    /**
     * @param  Query|array<string, scalar|null> $query
     * @return array<string, scalar|null>
     */
    protected function params(Query|array $query): array
    {
        return $query instanceof Query ? $query->toArray() : $query;
    }
}
