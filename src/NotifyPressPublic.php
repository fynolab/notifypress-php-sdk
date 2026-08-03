<?php

declare(strict_types=1);

namespace NotifyPress;

use NotifyPress\Exception\ConfigurationException;
use NotifyPress\Http\RequestOptions;
use NotifyPress\Http\Transport;
use NotifyPress\Model\PublicSubscriber;
use NotifyPress\Model\RecentNotification;
use NotifyPress\Payload\PublicSubscribe;

/**
 * The public, unauthenticated NotifyPress endpoints — the ones the hosted
 * widget uses for visitor opt-in.
 *
 * These are keyed by the site's **public** key (`npk_...`), which is safe to
 * expose, rather than the secret API token.
 *
 * ```php
 * $push = new NotifyPressPublic('npk_...', 'https://example.com');
 *
 * $push->subscribe(
 *     PublicSubscribe::make($fcmToken)->browser('Chrome')->device('Desktop')
 * );
 * ```
 *
 * **The site URL is required, and this is why:** the server validates every
 * request's `Origin`/`Referer` against the site's configured URL (exact host
 * or the `www.` variant — no other subdomains) and rejects requests carrying
 * neither. A browser sets that header itself; server-side PHP does not, so the
 * SDK sends it explicitly. Give the site URL registered in NotifyPress, or
 * every call will 403.
 */
final class NotifyPressPublic
{
    private readonly Transport $transport;

    private readonly string $publicKey;

    /**
     * @param string $publicKey The site's `npk_...` widget key
     * @param string $siteUrl   The site URL registered in NotifyPress; sent as
     *                          the Origin header on every request
     *
     * @throws ConfigurationException when either argument is empty
     */
    public function __construct(string $publicKey, string $siteUrl, ?Config $config = null)
    {
        if ($publicKey === '') {
            throw new ConfigurationException('A NotifyPress public site key (npk_...) is required.');
        }

        if ($siteUrl === '') {
            throw new ConfigurationException(
                'A site URL is required: the public endpoints reject requests whose Origin does not '
                . 'match the site registered in NotifyPress.'
            );
        }

        $origin = rtrim($siteUrl, '/');

        // No API token: these endpoints are deliberately unauthenticated, and
        // sending a secret to them would leak it for nothing.
        $this->publicKey = $publicKey;
        $this->transport = Transport::fromConfig(
            ($config ?? new Config())
                ->withApiToken('')
                ->withHeader('Origin', $origin)
                ->withHeader('Referer', $origin . '/')
        );
    }

    public static function make(string $publicKey, string $siteUrl, ?Config $config = null): self
    {
        return new self($publicKey, $siteUrl, $config);
    }

    /**
     * POST /public/sites/{publicKey}/subscribers — registers or re-activates a
     * subscriber, upserting on the FCM token. Rate limited.
     *
     * @param PublicSubscribe|array<string, mixed>|string $payload an FCM token, or a built payload
     */
    public function subscribe(
        PublicSubscribe|array|string $payload,
        ?RequestOptions $options = null,
    ): PublicSubscriber {
        if (is_string($payload)) {
            $payload = PublicSubscribe::make($payload);
        }

        $body = $payload instanceof PublicSubscribe ? $payload->toArray() : $payload;

        return $this->subscriberFrom(
            $this->transport->request(
                'POST',
                $this->path('/subscribers'),
                [],
                array_merge(['active' => true], $body),
                $options
            )
        );
    }

    /**
     * Marks a subscriber inactive. Same endpoint as subscribe(), with
     * `active: false` — the subscriber row is kept, so a later opt-in
     * re-activates it rather than creating a duplicate. Use erase() to delete.
     */
    public function unsubscribe(string $token, ?RequestOptions $options = null): PublicSubscriber
    {
        return $this->subscriberFrom(
            $this->transport->request(
                'POST',
                $this->path('/subscribers'),
                [],
                ['token' => $token, 'active' => false],
                $options
            )
        );
    }

    /**
     * GET /public/sites/{publicKey}/notifications/recent — the last three
     * notifications sent by the site.
     *
     * @return list<RecentNotification>
     */
    public function recentNotifications(?RequestOptions $options = null): array
    {
        $response = $this->transport->request(
            'GET',
            $this->path('/notifications/recent'),
            [],
            null,
            $options
        );

        $rows = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        $notifications = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $notifications[] = RecentNotification::fromArray($row);
            }
        }

        return $notifications;
    }

    /**
     * POST /public/sites/{publicKey}/subscribers/erase — GDPR hard delete.
     * Irreversible, and rate limited to 5/min.
     */
    public function erase(string $token, ?RequestOptions $options = null): bool
    {
        $response = $this->transport->request(
            'POST',
            $this->path('/subscribers/erase'),
            [],
            ['token' => $token],
            $options
        );

        return (bool) ($response['deleted'] ?? false);
    }

    /**
     * POST /notifications/{id}/click — click tracking. Needs no authentication.
     *
     * @return array<string, mixed>
     */
    public function trackClick(int $notificationId, ?RequestOptions $options = null): array
    {
        return $this->transport->request(
            'POST',
            '/notifications/' . $notificationId . '/click',
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
    public function trackView(
        int $notificationId,
        array $details = [],
        ?RequestOptions $options = null,
    ): array {
        return $this->transport->request(
            'POST',
            '/notifications/' . $notificationId . '/track-view',
            [],
            array_merge(['viewed' => true], $details),
            $options
        );
    }

    public function transport(): Transport
    {
        return $this->transport;
    }

    private function path(string $suffix): string
    {
        return '/public/sites/' . rawurlencode($this->publicKey) . $suffix;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function subscriberFrom(array $response): PublicSubscriber
    {
        $data = $response['data'] ?? null;

        /** @var array<string, mixed> $data */
        $data = is_array($data) ? $data : $response;

        return PublicSubscriber::fromArray($data);
    }
}
