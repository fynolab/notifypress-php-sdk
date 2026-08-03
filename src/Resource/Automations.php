<?php

declare(strict_types=1);

namespace NotifyPress\Resource;

use NotifyPress\Http\RequestOptions;
use NotifyPress\Model\Automation;
use NotifyPress\Model\Notification;
use NotifyPress\Pagination\OffsetPage;
use NotifyPress\Pagination\ResultSet;
use NotifyPress\Payload\AutomationPayload;
use NotifyPress\Query\Query;

/**
 * Automation workflows.
 *
 * Every method except list() requires a paid subscription; a free account gets
 * a PaidPlanException carrying the upgrade URL.
 */
final class Automations extends Resource
{
    /**
     * GET /automations — offset-paginated (page numbers, not cursors) and not
     * paid-gated, so a client can render an upgrade prompt from
     * `$page->isPaidUser()` instead of catching a 403.
     *
     * @param  Query|array<string, scalar|null> $query
     * @return OffsetPage<Automation>
     */
    public function list(Query|array $query = [], ?RequestOptions $options = null): OffsetPage
    {
        return $this->offsetPage(
            '/automations',
            $this->params($query),
            Automation::fromArray(...),
            $options
        );
    }

    /**
     * POST /automations/trigger — fires a custom event, enrolling the
     * subscriber in every ACTIVE automation listening for it.
     *
     * @return array<string, mixed>
     */
    public function trigger(string $event, string $subscriberToken, ?RequestOptions $options = null): array
    {
        return $this->transport->request(
            'POST',
            '/automations/trigger',
            [],
            ['event' => $event, 'subscriber_token' => $subscriberToken],
            $options
        );
    }

    /**
     * GET /automations/templates — the pre-built templates gallery.
     *
     * Templates are code-owned server-side config rather than records, so they
     * are returned as raw arrays.
     *
     * @return ResultSet<array<string, mixed>>
     */
    public function templates(?RequestOptions $options = null): ResultSet
    {
        return $this->resultSet(
            '/automations/templates',
            [],
            static fn (array $template): array => $template,
            $options
        );
    }

    /**
     * GET /automations/messages — the reusable messages library.
     *
     * Messages are notifications with `purpose = automation_message`; they can
     * be used inside a SEND_NOTIFICATION step but never sent as campaigns.
     *
     * @param  Query|array<string, scalar|null> $query
     * @return ResultSet<Notification>
     */
    public function messages(Query|array $query = [], ?RequestOptions $options = null): ResultSet
    {
        return $this->resultSet(
            '/automations/messages',
            $this->params($query),
            Notification::fromArray(...),
            $options
        );
    }

    /**
     * GET /automations/{id} — includes the step tree.
     */
    public function retrieve(int $id, ?RequestOptions $options = null): Automation
    {
        return $this->hydrateEnvelope(
            $this->transport->request('GET', '/automations/' . $id, [], null, $options),
            Automation::fromArray(...)
        );
    }

    /**
     * POST /automations
     *
     * @param AutomationPayload|array<string, mixed> $payload
     */
    public function create(AutomationPayload|array $payload, ?RequestOptions $options = null): Automation
    {
        return $this->hydrateEnvelope(
            $this->transport->request('POST', '/automations', [], $this->body($payload), $options),
            Automation::fromArray(...)
        );
    }

    /**
     * PUT /automations/{id}
     *
     * @param AutomationPayload|array<string, mixed> $payload
     */
    public function update(
        int $id,
        AutomationPayload|array $payload,
        ?RequestOptions $options = null,
    ): Automation {
        return $this->hydrateEnvelope(
            $this->transport->request('PUT', '/automations/' . $id, [], $this->body($payload), $options),
            Automation::fromArray(...)
        );
    }

    /**
     * DELETE /automations/{id}
     *
     * @return array<string, mixed>
     */
    public function delete(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('DELETE', '/automations/' . $id, [], null, $options);
    }

    /**
     * POST /automations/{id}/activate — requires at least one step (422 otherwise).
     *
     * @return array<string, mixed>
     */
    public function activate(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('POST', '/automations/' . $id . '/activate', [], null, $options);
    }

    /**
     * POST /automations/{id}/pause
     *
     * @return array<string, mixed>
     */
    public function pause(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('POST', '/automations/' . $id . '/pause', [], null, $options);
    }

    /**
     * POST /automations/{id}/archive — one-way. There is no un-archive; use
     * duplicate() to start the workflow again as a fresh draft.
     *
     * @return array<string, mixed>
     */
    public function archive(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('POST', '/automations/' . $id . '/archive', [], null, $options);
    }

    /**
     * POST /automations/{id}/duplicate — clones the automation and its steps
     * as a new DRAFT, without enrollments.
     *
     * @return array<string, mixed>
     */
    public function duplicate(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('POST', '/automations/' . $id . '/duplicate', [], null, $options);
    }
}
