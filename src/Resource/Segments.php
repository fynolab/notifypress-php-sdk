<?php

declare(strict_types=1);

namespace NotifyPress\Resource;

use NotifyPress\Http\RequestOptions;
use NotifyPress\Model\Segment;
use NotifyPress\Pagination\ResultSet;
use NotifyPress\Payload\SegmentPayload;
use NotifyPress\Payload\SegmentRule;

final class Segments extends Resource
{
    /**
     * GET /segments — every segment on the current site.
     *
     * The response also reports whether the account is on a paid plan, so a
     * client can render a locked state rather than inferring it from a 403:
     * `$client->segments->list()->isPaidUser()`.
     *
     * @return ResultSet<Segment>
     */
    public function list(?RequestOptions $options = null): ResultSet
    {
        return $this->resultSet('/segments', [], Segment::fromArray(...), $options);
    }

    /**
     * GET /segments/{id}
     */
    public function retrieve(int $id, ?RequestOptions $options = null): Segment
    {
        return $this->hydrateEnvelope(
            $this->transport->request('GET', '/segments/' . $id, [], null, $options),
            Segment::fromArray(...)
        );
    }

    /**
     * POST /segments — max 100 segments per site (plan limits may be lower).
     *
     * @param SegmentPayload|array<string, mixed> $payload
     */
    public function create(SegmentPayload|array $payload, ?RequestOptions $options = null): Segment
    {
        return $this->hydrateEnvelope(
            $this->transport->request('POST', '/segments', [], $this->body($payload), $options),
            Segment::fromArray(...)
        );
    }

    /**
     * PUT /segments/{id}
     *
     * @param SegmentPayload|array<string, mixed> $payload
     */
    public function update(int $id, SegmentPayload|array $payload, ?RequestOptions $options = null): Segment
    {
        return $this->hydrateEnvelope(
            $this->transport->request('PUT', '/segments/' . $id, [], $this->body($payload), $options),
            Segment::fromArray(...)
        );
    }

    /**
     * DELETE /segments/{id}
     *
     * @return array<string, mixed>
     */
    public function delete(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('DELETE', '/segments/' . $id, [], null, $options);
    }

    /**
     * GET /segments/meta — the filter values available for rules
     * (countries, devices, browsers seen on this site).
     *
     * @return array<string, mixed>
     */
    public function meta(?RequestOptions $options = null): array
    {
        return $this->transport->request('GET', '/segments/meta', [], null, $options);
    }

    /**
     * POST /segments/preview-count — how many subscribers a rule matches
     * right now, without saving it.
     *
     * @param SegmentRule|array<string, mixed> $rule
     */
    public function previewCount(SegmentRule|array $rule, ?RequestOptions $options = null): int
    {
        $response = $this->transport->request(
            'POST',
            '/segments/preview-count',
            [],
            ['rule' => $rule instanceof SegmentRule ? $rule->toArray() : $rule],
            $options
        );

        return is_numeric($response['count'] ?? null) ? (int) $response['count'] : 0;
    }
}
