<?php

declare(strict_types=1);

namespace NotifyPress\Http\Client;

use NotifyPress\Exception\ConnectionException;
use NotifyPress\Exception\TimeoutException;
use NotifyPress\Http\HttpClientInterface;
use NotifyPress\Http\Request;
use NotifyPress\Http\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Adapts any PSR-18 client to the SDK's transport seam.
 *
 * This is what makes the SDK use Guzzle automatically inside Laravel, and it
 * is the extension point for anyone who wants their own client (custom
 * middleware, instrumentation, a mock in tests).
 */
final class Psr18Client implements HttpClientInterface
{
    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly ClientInterface $client,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        if ($requestFactory !== null && $streamFactory !== null) {
            $this->requestFactory = $requestFactory;
            $this->streamFactory = $streamFactory;

            return;
        }

        $located = Psr17FactoryLocator::findOrFail();

        /** @var RequestFactoryInterface $located */
        $this->requestFactory = $requestFactory ?? $located;
        /** @var StreamFactoryInterface $located */
        $this->streamFactory = $streamFactory ?? $located;
    }

    public function send(Request $request): Response
    {
        $psrRequest = $this->requestFactory->createRequest($request->method, $request->uri);

        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        if ($request->body !== null) {
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($request->body));
        }

        try {
            $psrResponse = $this->client->sendRequest($psrRequest);
        } catch (ClientExceptionInterface $e) {
            // PSR-18 has no dedicated timeout exception, so the message is the
            // only signal available for telling the two failure modes apart.
            if (stripos($e->getMessage(), 'timed out') !== false || stripos($e->getMessage(), 'timeout') !== false) {
                throw new TimeoutException(sprintf(
                    'Request timed out after %.1fs: %s %s',
                    $request->timeout,
                    $request->method,
                    $request->uri
                ), 0, $e);
            }

            throw new ConnectionException(
                sprintf('HTTP request failed: %s', $e->getMessage()),
                0,
                $e
            );
        }

        $headers = [];

        foreach ($psrResponse->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return new Response(
            $psrResponse->getStatusCode(),
            $headers,
            (string) $psrResponse->getBody()
        );
    }
}
