<?php

declare(strict_types=1);

namespace NotifyPress;

use NotifyPress\Exception\ConfigurationException;
use NotifyPress\Http\HttpClientInterface;
use NotifyPress\Http\Transport;
use NotifyPress\Resource\Announcements;
use NotifyPress\Resource\Automations;
use NotifyPress\Resource\Dashboard;
use NotifyPress\Resource\Notifications;
use NotifyPress\Resource\Reports;
use NotifyPress\Resource\Segments;
use NotifyPress\Resource\Sites;
use NotifyPress\Resource\Subscribers;
use NotifyPress\Resource\Tags;

/**
 * The authenticated NotifyPress API client.
 *
 * ```php
 * use NotifyPress\NotifyPress;
 * use NotifyPress\Payload\SendNotification;
 *
 * $client = new NotifyPress(getenv('NOTIFYPRESS_API_TOKEN'));
 *
 * $client->notifications->send(
 *     SendNotification::make('Flash sale', '25% off, today only')
 *         ->targetUrl('https://example.com/sale')
 * );
 * ```
 *
 * The API token is a site secret in the form `{id}|{secret}`. Keep it on the
 * server — never ship it to a browser. For browser-side opt-in use
 * {@see NotifyPressPublic} with the site's public `npk_` key instead.
 *
 * No HTTP client needs configuring: WordPress, Guzzle, cURL, and PHP streams
 * are detected in that order. Pass one explicitly only to override that.
 */
final class NotifyPress
{
    public readonly Sites $sites;

    public readonly Notifications $notifications;

    public readonly Subscribers $subscribers;

    public readonly Tags $tags;

    public readonly Segments $segments;

    public readonly Automations $automations;

    public readonly Reports $reports;

    public readonly Announcements $announcements;

    public readonly Dashboard $dashboard;

    private readonly Config $config;

    private readonly Transport $transport;

    /**
     * @param string|Config $token An API token, or a fully-built Config for
     *                             anything beyond the defaults
     *
     * @throws ConfigurationException when the token is empty, or no HTTP client is available
     */
    public function __construct(string|Config $token)
    {
        $config = is_string($token) ? Config::make($token) : $token;

        if ($config->apiToken === '') {
            throw new ConfigurationException(
                'A NotifyPress API token is required. Create one under Site settings → API token.'
            );
        }

        $this->config = $config;
        $this->transport = Transport::fromConfig($config);

        $this->sites = new Sites($this->transport);
        $this->notifications = new Notifications($this->transport);
        $this->subscribers = new Subscribers($this->transport);
        $this->tags = new Tags($this->transport);
        $this->segments = new Segments($this->transport);
        $this->automations = new Automations($this->transport);
        $this->reports = new Reports($this->transport);
        $this->announcements = new Announcements($this->transport);
        $this->dashboard = new Dashboard($this->transport);
    }

    /**
     * Builds a client from a Config, for callers who prefer a named entry point.
     */
    public static function make(string|Config $token): self
    {
        return new self($token);
    }

    /**
     * A copy of this client pointed at a different base URL — a staging
     * environment, or a local Sail instance.
     */
    public function withBaseUrl(string $baseUrl): self
    {
        return new self($this->config->withBaseUrl($baseUrl));
    }

    /**
     * A copy of this client using a different API token, and so a different
     * site. Everything else is preserved.
     */
    public function withApiToken(string $apiToken): self
    {
        return new self($this->config->withApiToken($apiToken));
    }

    public function withTimeout(float $seconds): self
    {
        return new self($this->config->withTimeout($seconds));
    }

    public function withHttpClient(HttpClientInterface $client): self
    {
        return new self($this->config->withHttpClient($client));
    }

    public function config(): Config
    {
        return $this->config;
    }

    /**
     * The shared transport, for calling an endpoint this SDK does not model yet.
     */
    public function transport(): Transport
    {
        return $this->transport;
    }
}
