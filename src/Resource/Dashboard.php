<?php

declare(strict_types=1);

namespace NotifyPress\Resource;

use NotifyPress\Http\RequestOptions;

/**
 * The overview and analytics endpoints.
 *
 * Both return loosely-shaped aggregate payloads that change with the product's
 * dashboard, so they are handed back as arrays rather than pinned to a DTO
 * that would need a release every time a tile is added.
 */
final class Dashboard extends Resource
{
    /**
     * GET /dashboard — overview metrics. Totals arrive as display strings
     * (e.g. "1.2K"), not integers.
     *
     * @return array<string, mixed>
     */
    public function get(?RequestOptions $options = null): array
    {
        $response = $this->transport->request('GET', '/dashboard', [], null, $options);

        return $this->unwrap($response);
    }

    /**
     * GET /analytics — engagement metrics, plus `isPaidUser`.
     *
     * @return array<string, mixed>
     */
    public function analytics(?RequestOptions $options = null): array
    {
        $response = $this->transport->request('GET', '/analytics', [], null, $options);

        return $this->unwrap($response);
    }

    /**
     * @param  array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function unwrap(array $response): array
    {
        $data = $response['data'] ?? null;

        if (! is_array($data)) {
            return $response;
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
