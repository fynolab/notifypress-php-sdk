<?php

declare(strict_types=1);

namespace NotifyPress\Resource;

use NotifyPress\Exception\NotFoundException;
use NotifyPress\Http\RequestOptions;
use NotifyPress\Model\Site;
use NotifyPress\Pagination\CursorPage;
use NotifyPress\Payload\SiteUpdate;

final class Sites extends Resource
{
    /**
     * GET /sites — every site on the account, cursor-paginated 10 at a time.
     *
     * @return CursorPage<Site>
     */
    public function list(?RequestOptions $options = null): CursorPage
    {
        return $this->cursorPage('/sites', [], Site::fromArray(...), $options);
    }

    /**
     * GET /sites/current — the site the API token belongs to.
     *
     * @throws NotFoundException when the token has no linked site
     */
    public function current(?RequestOptions $options = null): Site
    {
        return $this->hydrateEnvelope(
            $this->transport->request('GET', '/sites/current', [], null, $options),
            Site::fromArray(...)
        );
    }

    /**
     * GET /sites/{id}
     */
    public function retrieve(int $id, ?RequestOptions $options = null): Site
    {
        return $this->hydrateEnvelope(
            $this->transport->request('GET', '/sites/' . $id, [], null, $options),
            Site::fromArray(...)
        );
    }

    /**
     * PUT /sites/current
     *
     * @param SiteUpdate|array<string, mixed> $payload
     */
    public function updateCurrent(SiteUpdate|array $payload, ?RequestOptions $options = null): Site
    {
        return $this->hydrateEnvelope(
            $this->transport->request('PUT', '/sites/current', [], $this->body($payload), $options),
            Site::fromArray(...)
        );
    }

    /**
     * PUT /sites/{id}
     *
     * @param SiteUpdate|array<string, mixed> $payload
     */
    public function update(int $id, SiteUpdate|array $payload, ?RequestOptions $options = null): Site
    {
        return $this->hydrateEnvelope(
            $this->transport->request('PUT', '/sites/' . $id, [], $this->body($payload), $options),
            Site::fromArray(...)
        );
    }
}
