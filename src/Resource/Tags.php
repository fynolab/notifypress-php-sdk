<?php

declare(strict_types=1);

namespace NotifyPress\Resource;

use NotifyPress\Http\RequestOptions;
use NotifyPress\Model\Tag;
use NotifyPress\Pagination\ResultSet;
use NotifyPress\Payload\TagCreate;

final class Tags extends Resource
{
    /**
     * GET /tags — every tag on the current site, with subscriber counts.
     *
     * @return ResultSet<Tag>
     */
    public function list(?RequestOptions $options = null): ResultSet
    {
        return $this->resultSet('/tags', [], Tag::fromArray(...), $options);
    }

    /**
     * POST /tags — a site may hold up to 200 tags. Tags are a paid feature: on the Free plan
     * this throws PaidPlanException (listing and deleting tags stay open).
     *
     * @param TagCreate|array<string, mixed> $payload
     */
    public function create(TagCreate|array $payload, ?RequestOptions $options = null): Tag
    {
        return $this->hydrateEnvelope(
            $this->transport->request('POST', '/tags', [], $this->body($payload), $options),
            Tag::fromArray(...)
        );
    }

    /**
     * DELETE /tags/{id}
     *
     * @return array<string, mixed>
     */
    public function delete(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('DELETE', '/tags/' . $id, [], null, $options);
    }
}
