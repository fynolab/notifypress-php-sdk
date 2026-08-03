<?php

declare(strict_types=1);

namespace NotifyPress\Resource;

use DateTimeInterface;
use NotifyPress\Http\Multipart\MultipartBody;
use NotifyPress\Http\RequestOptions;
use NotifyPress\Model\Notification;
use NotifyPress\Model\SendResult;
use NotifyPress\Pagination\CursorPage;
use NotifyPress\Payload\NotificationDraft;
use NotifyPress\Payload\RecurringSchedule;
use NotifyPress\Payload\ScheduleNotification;
use NotifyPress\Payload\SendNotification;
use NotifyPress\Query\Query;

final class Notifications extends Resource
{
    /** Targets a single subscriber on send(). */
    public const SUBSCRIPTION_TOKEN_HEADER = 'notifypress-subscription-token';

    /**
     * GET /notifications — cursor-paginated campaigns.
     *
     * List rows arrive truncated by the server (title 20 chars, message 50);
     * call retrieve() for the full values.
     *
     * @param  Query|array<string, scalar|null> $query
     * @return CursorPage<Notification>
     */
    public function list(Query|array $query = [], ?RequestOptions $options = null): CursorPage
    {
        return $this->cursorPage(
            '/notifications',
            $this->params($query),
            Notification::fromArray(...),
            $options
        );
    }

    /**
     * GET /notifications/live-activity — sends currently in flight.
     *
     * @return array<string, mixed>
     */
    public function liveActivity(?RequestOptions $options = null): array
    {
        return $this->transport->request('GET', '/notifications/live-activity', [], null, $options);
    }

    /**
     * POST /notifications — creates a draft.
     *
     * Pass a MultipartBody to upload an icon (max 2 MB) or image (max 4 MB)
     * instead of referencing one by URL.
     *
     * @param NotificationDraft|array<string, mixed>|MultipartBody $payload
     */
    public function create(
        NotificationDraft|array|MultipartBody $payload,
        ?RequestOptions $options = null,
    ): Notification {
        return $this->hydrateEnvelope(
            $this->transport->request('POST', '/notifications', [], $this->body($payload), $options),
            Notification::fromArray(...)
        );
    }

    /**
     * GET /notifications/{id} — full record including analytics.
     */
    public function retrieve(int $id, ?RequestOptions $options = null): Notification
    {
        return $this->hydrateEnvelope(
            $this->transport->request('GET', '/notifications/' . $id, [], null, $options),
            Notification::fromArray(...)
        );
    }

    /**
     * PUT /notifications/{id}
     *
     * @param NotificationDraft|array<string, mixed>|MultipartBody $payload
     */
    public function update(
        int $id,
        NotificationDraft|array|MultipartBody $payload,
        ?RequestOptions $options = null,
    ): Notification {
        return $this->hydrateEnvelope(
            $this->transport->request('PUT', '/notifications/' . $id, [], $this->body($payload), $options),
            Notification::fromArray(...)
        );
    }

    /**
     * DELETE /notifications/{id}
     *
     * @return array<string, mixed>
     */
    public function delete(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('DELETE', '/notifications/' . $id, [], null, $options);
    }

    /**
     * POST /notifications/send — sends immediately. Rate limited to 50/min.
     *
     * Never retried automatically: a send that times out may already have gone
     * out, and a retry would push a duplicate to the whole audience.
     *
     * @param SendNotification|array<string, mixed> $payload
     * @param string|null                           $subscriptionToken FCM token; when set, only that
     *                                                                 subscriber receives the notification
     */
    public function send(
        SendNotification|array $payload,
        ?string $subscriptionToken = null,
        ?RequestOptions $options = null,
    ): SendResult {
        $options ??= new RequestOptions();

        if ($subscriptionToken !== null) {
            $options = $options->withHeader(self::SUBSCRIPTION_TOKEN_HEADER, $subscriptionToken);
        }

        return SendResult::fromArray(
            $this->transport->request('POST', '/notifications/send', [], $this->body($payload), $options)
        );
    }

    /**
     * POST /notifications/schedule — creates and schedules in one call.
     *
     * @param ScheduleNotification|array<string, mixed> $payload
     */
    public function schedule(
        ScheduleNotification|array $payload,
        ?RequestOptions $options = null,
    ): SendResult {
        return SendResult::fromArray(
            $this->transport->request('POST', '/notifications/schedule', [], $this->body($payload), $options)
        );
    }

    /**
     * POST /notifications/{id}/schedule — schedules an existing draft.
     *
     * $scheduledAt is interpreted in the account's display timezone, not UTC.
     * A DateTime is formatted exactly as given, with no conversion.
     */
    public function scheduleDraft(
        int $id,
        DateTimeInterface|string $scheduledAt,
        ?RequestOptions $options = null,
    ): SendResult {
        $value = $scheduledAt instanceof DateTimeInterface
            ? $scheduledAt->format(ScheduleNotification::DATE_FORMAT)
            : $scheduledAt;

        return SendResult::fromArray($this->transport->request(
            'POST',
            '/notifications/' . $id . '/schedule',
            [],
            ['scheduled_at' => $value],
            $options
        ));
    }

    /**
     * POST /notifications/{id}/recurring-send
     *
     * @param RecurringSchedule|array<string, mixed> $payload
     */
    public function recurringSend(
        int $id,
        RecurringSchedule|array $payload,
        ?RequestOptions $options = null,
    ): SendResult {
        return SendResult::fromArray($this->transport->request(
            'POST',
            '/notifications/' . $id . '/recurring-send',
            [],
            $this->body($payload),
            $options
        ));
    }

    /**
     * POST /notifications/{id}/cancel — only valid while SENDING or SCHEDULED.
     *
     * @return array<string, mixed>
     */
    public function cancel(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request('POST', '/notifications/' . $id . '/cancel', [], null, $options);
    }

    /**
     * POST /notifications/{id}/click — click tracking. Needs no authentication.
     *
     * @return array<string, mixed>
     */
    public function trackClick(int $id, ?RequestOptions $options = null): array
    {
        return $this->transport->request(
            'POST',
            '/notifications/' . $id . '/click',
            [],
            ['clicked' => true],
            $options
        );
    }

    /**
     * POST /notifications/{id}/track-view — view tracking. Needs no authentication.
     *
     * @param  array<string, mixed> $details
     * @return array<string, mixed>
     */
    public function trackView(int $id, array $details = [], ?RequestOptions $options = null): array
    {
        return $this->transport->request(
            'POST',
            '/notifications/' . $id . '/track-view',
            [],
            array_merge(['viewed' => true], $details),
            $options
        );
    }
}
