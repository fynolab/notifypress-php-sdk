<?php

declare(strict_types=1);

namespace NotifyPress\Resource;

use NotifyPress\Http\RequestOptions;
use NotifyPress\Model\Announcement;
use NotifyPress\Pagination\ResultSet;

final class Announcements extends Resource
{
    /**
     * GET /announcements — product announcements for the account.
     *
     * The unread badge count comes back on the envelope:
     * `$client->announcements->list()->meta('unread_count')`.
     *
     * @return ResultSet<Announcement>
     */
    public function list(?RequestOptions $options = null): ResultSet
    {
        return $this->resultSet('/announcements', [], Announcement::fromArray(...), $options);
    }

    /**
     * POST /announcements/read-all
     *
     * @return array<string, mixed>
     */
    public function markAllRead(?RequestOptions $options = null): array
    {
        return $this->transport->request('POST', '/announcements/read-all', [], null, $options);
    }

    /**
     * POST /announcements/{id}/read
     *
     * @return array<string, mixed>
     */
    public function markRead(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('POST', '/announcements/' . $id . '/read', [], null, $options);
    }
}
