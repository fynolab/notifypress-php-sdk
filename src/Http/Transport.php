<?php

declare(strict_types=1);

namespace NotifyPress\Http;

use NotifyPress\Config;
use NotifyPress\Exception\ApiException;
use NotifyPress\Exception\ConnectionException;
use NotifyPress\Exception\ErrorFactory;
use NotifyPress\Exception\TimeoutException;
use NotifyPress\Http\Client\ClientFactory;
use NotifyPress\Http\Multipart\MultipartBody;
use NotifyPress\Support\Json;
use NotifyPress\Version;

/**
 * The single place that turns an SDK method call into an HTTP exchange.
 *
 * Owns URL building, authentication, encoding, retries, and error mapping,
 * so no resource class ever touches an HTTP detail.
 */
final class Transport
{
    /**
     * Methods retried automatically. Everything else must opt in: a
     * notification send that times out may still have gone out, and retrying
     * would push a duplicate to the entire audience.
     */
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD'];

    public function __construct(
        private readonly Config $config,
        private readonly HttpClientInterface $client,
        private readonly RetryPolicy $retryPolicy,
        private readonly Sleeper $sleeper,
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            $config,
            $config->httpClient ?? ClientFactory::detect(),
            new RetryPolicy($config->maxRetries),
            new RealSleeper(),
        );
    }

    public function config(): Config
    {
        return $this->config;
    }

    /**
     * Performs a request and decodes the JSON body.
     *
     * A 204, or any empty body, decodes to an empty array rather than null, so
     * callers never have to null-check a successful response.
     *
     * @param  array<string, scalar|null>             $query
     * @param  array<string, mixed>|MultipartBody|null $body
     * @return array<string, mixed>
     *
     * @throws ApiException        on any non-2xx response
     * @throws TimeoutException    when the request exceeds the timeout
     * @throws ConnectionException when the request never reached the server
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        array|MultipartBody|null $body = null,
        ?RequestOptions $options = null,
    ): array {
        $response = $this->requestRaw($method, $path, $query, $body, $options);

        if ($response->status === 204 || trim($response->body) === '') {
            return [];
        }

        return Json::toArray(Json::decode($response->body));
    }

    /**
     * Performs a request and returns the untouched Response. Used for binary
     * payloads such as generated PDF reports.
     *
     * @param array<string, scalar|null>             $query
     * @param array<string, mixed>|MultipartBody|null $body
     */
    public function requestRaw(
        string $method,
        string $path,
        array $query = [],
        array|MultipartBody|null $body = null,
        ?RequestOptions $options = null,
    ): Response {
        $method = $method === '' ? 'GET' : strtoupper($method);
        $options ??= new RequestOptions();

        $request = new Request(
            $method,
            $this->url($path, $query),
            $this->headers($method, $body, $options),
            $this->body($body),
            $options->timeout ?? $this->config->timeout,
            $this->config->connectTimeout,
            $this->config->verifySsl,
        );

        return $this->send($request, $options->retryable ?? in_array($method, self::IDEMPOTENT_METHODS, true));
    }

    /**
     * @throws ApiException
     * @throws TimeoutException
     * @throws ConnectionException
     */
    private function send(Request $request, bool $retryable): Response
    {
        $attempt = 0;

        while (true) {
            try {
                $response = $this->client->send($request);
            } catch (TimeoutException|ConnectionException $exception) {
                if (! $retryable || ! $this->retryPolicy->shouldRetryConnectionFailure($attempt)) {
                    throw $exception;
                }

                $this->sleeper->sleep($this->retryPolicy->delayFor($attempt));
                $attempt++;

                continue;
            }

            if ($response->isSuccessful()) {
                return $response;
            }

            if (! $retryable || ! $this->retryPolicy->shouldRetryStatus($response->status, $attempt)) {
                throw ErrorFactory::create($response);
            }

            $this->sleeper->sleep($this->retryPolicy->delayFor($attempt, $response));
            $attempt++;
        }
    }

    /**
     * @param  array<string, scalar|null> $query
     * @return non-empty-string
     */
    private function url(string $path, array $query): string
    {
        $base = rtrim($this->config->baseUrl, '/');
        $suffix = '/' . ltrim($path, '/');

        return $base . $suffix . Query::toString($query);
    }

    /**
     * @param  array<string, mixed>|MultipartBody|null $body
     * @return array<string, string>
     */
    private function headers(string $method, array|MultipartBody|null $body, RequestOptions $options): array
    {
        $headers = array_merge(
            [
                'Accept' => 'application/json',
                'User-Agent' => Version::userAgent(),
            ],
            $this->config->defaultHeaders
        );

        if ($this->config->apiToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->config->apiToken;
        }

        if ($body instanceof MultipartBody) {
            $headers['Content-Type'] = $body->contentType();
        } elseif ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        return array_merge($headers, $options->headers);
    }

    /**
     * @param array<string, mixed>|MultipartBody|null $body
     */
    private function body(array|MultipartBody|null $body): ?string
    {
        if ($body instanceof MultipartBody) {
            return $body->toString();
        }

        if ($body === null) {
            return null;
        }

        return Json::encode($body);
    }
}
