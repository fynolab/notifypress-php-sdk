<?php

declare(strict_types=1);

namespace NotifyPress\Resource;

use NotifyPress\Http\RequestOptions;
use NotifyPress\Model\Subscriber;
use NotifyPress\Pagination\CursorPage;
use NotifyPress\Payload\SubscriberCreate;
use NotifyPress\Query\Query;

final class Subscribers extends Resource
{
    /**
     * GET /subscribers — cursor-paginated. The first page also carries `total`,
     * a cached count of the whole set, on CursorPage::$total.
     *
     * @param  Query|array<string, scalar|null> $query
     * @return CursorPage<Subscriber>
     */
    public function list(Query|array $query = [], ?RequestOptions $options = null): CursorPage
    {
        return $this->cursorPage(
            '/subscribers',
            $this->params($query),
            Subscriber::fromArray(...),
            $options
        );
    }

    /**
     * POST /subscribers — upserts on the FCM token.
     *
     * @param SubscriberCreate|array<string, mixed> $payload
     */
    public function create(SubscriberCreate|array $payload, ?RequestOptions $options = null): Subscriber
    {
        return $this->hydrateEnvelope(
            $this->transport->request('POST', '/subscribers', [], $this->body($payload), $options),
            Subscriber::fromArray(...)
        );
    }

    /**
     * DELETE /subscribers/{id}
     *
     * Takes the numeric subscriber ID — not the FCM token, unlike the tag
     * endpoints below.
     *
     * @return array<string, mixed>
     */
    public function delete(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('DELETE', '/subscribers/' . $id, [], null, $options);
    }

    /**
     * POST /subscribers/{token}/tags — attaches an existing tag. Tagging is a paid feature: on
     * the Free plan this throws PaidPlanException. removeTag() stays open. The list page's
     * isPaidUser() tells you up front.
     *
     * @return array<string, mixed>
     */
    public function addTag(string $token, int $tagId, ?RequestOptions $options = null): array
    {
        return $this->transport->request(
            'POST',
            '/subscribers/' . rawurlencode($token) . '/tags',
            [],
            ['tag_id' => $tagId],
            $options
        );
    }

    /**
     * DELETE /subscribers/{token}/tags/{tag}
     *
     * @return array<string, mixed>
     */
    public function removeTag(string $token, int $tagId, ?RequestOptions $options = null): array
    {
        return $this->transport->request(
            'DELETE',
            '/subscribers/' . rawurlencode($token) . '/tags/' . $tagId,
            [],
            null,
            $options
        );
    }
}
