<?php

declare(strict_types=1);

namespace NotifyPress\Http\Client;

use GuzzleHttp\Client as GuzzleClient;
use NotifyPress\Exception\ConfigurationException;
use NotifyPress\Http\HttpClientInterface;

/**
 * Picks the best available HTTP client for the current environment.
 *
 * This is what lets one SDK run unchanged in WordPress, Laravel, and plain
 * PHP: nothing has to be configured, and nothing has to be installed.
 */
final class ClientFactory
{
    private function __construct()
    {
    }

    /**
     * Detection order, most environment-specific first:
     *
     *   1. WordPress — wp_remote_request() respects proxy settings, request
     *      filters, and external-request blocking that a raw socket would
     *      bypass. Inside WordPress that is always the right choice.
     *   2. Guzzle — already present in Laravel, and PSR-18 native, so an
     *      application's existing HTTP stack gets reused.
     *   3. cURL, then streams — the dependency-free fallbacks.
     *
     * @throws ConfigurationException when no client can be constructed
     */
    public static function detect(): HttpClientInterface
    {
        if (WordPressClient::isSupported()) {
            return new WordPressClient();
        }

        if (class_exists(GuzzleClient::class) && Psr17FactoryLocator::isAvailable()) {
            return new Psr18Client(new GuzzleClient());
        }

        if (CurlClient::isSupported()) {
            return new CurlClient();
        }

        if (StreamClient::isSupported()) {
            return new StreamClient();
        }

        throw new ConfigurationException(
            'No usable HTTP client found. Enable ext-curl, enable allow_url_fopen, '
            . 'or pass your own PSR-18 client to the NotifyPress constructor.'
        );
    }
}
