<?php

declare(strict_types=1);

namespace NotifyPress\Http\Client;

use NotifyPress\Exception\ConfigurationException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Finds an installed PSR-17 factory.
 *
 * Deliberately a handful of class_exists() checks rather than a dependency on
 * php-http/discovery: that package is a Composer *plugin*, and this SDK ends
 * up committed inside a WordPress plugin's vendor/ directory where install-time
 * machinery is a liability.
 *
 * Every PSR-18 client in practice ships a PSR-17 implementation alongside it
 * (Guzzle bundles guzzlehttp/psr7), so this rarely has to look far.
 */
final class Psr17FactoryLocator
{
    /**
     * Written as strings rather than ::class constants on purpose: these are
     * optional, and naming a class the SDK does not depend on would make every
     * static analyser demand it be installed.
     *
     * @var list<string>
     */
    private static array $candidates = [
        'GuzzleHttp\\Psr7\\HttpFactory',
        'Nyholm\\Psr7\\Factory\\Psr17Factory',
        'Laminas\\Diactoros\\RequestFactory',
        'Slim\\Psr7\\Factory\\RequestFactory',
    ];

    private function __construct()
    {
    }

    /**
     * Adds a PSR-17 implementation the SDK does not know about, so an
     * application using an unusual one still gets automatic detection.
     *
     * @param string $factory Class name implementing both the PSR-17 request
     *                        and stream factory interfaces
     */
    public static function register(string $factory): void
    {
        if (! in_array($factory, self::$candidates, true)) {
            array_unshift(self::$candidates, $factory);
        }
    }

    public static function isAvailable(): bool
    {
        return self::find() !== null;
    }

    /**
     * @return object|null An instance implementing both PSR-17 factory interfaces
     */
    public static function find(): ?object
    {
        foreach (self::$candidates as $candidate) {
            // Check the class rather than an instance: a candidate that only
            // implements half of PSR-17 is no use here, and there is no reason
            // to construct it to find that out.
            if (! class_exists($candidate)
                || ! is_a($candidate, RequestFactoryInterface::class, true)
                || ! is_a($candidate, StreamFactoryInterface::class, true)) {
                continue;
            }

            return new $candidate();
        }

        return null;
    }

    /**
     * @throws ConfigurationException when nothing suitable is installed
     */
    public static function findOrFail(): object
    {
        $factory = self::find();

        if ($factory === null) {
            throw new ConfigurationException(
                'No PSR-17 HTTP factory was found. Install one (for example "composer require nyholm/psr7") '
                . 'or pass explicit factories to Psr18Client.'
            );
        }

        return $factory;
    }
}
