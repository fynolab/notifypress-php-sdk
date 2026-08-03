<?php

declare(strict_types=1);

namespace NotifyPress\Tests\Support;

use NotifyPress\Http\HttpClientInterface;
use NotifyPress\Http\Request;
use NotifyPress\Http\Response;
use NotifyPress\Support\Json;
use RuntimeException;
use Throwable;

/**
 * An HTTP client that answers from a queue and records what it was asked.
 *
 * The whole suite runs against this — no test touches the network.
 */
final class MockHttpClient implements HttpClientInterface
{
    /** @var list<Request> */
    public array $requests = [];

    /** @var list<Response|Throwable> */
    private array $queue = [];

    public static function make(): self
    {
        return new self();
    }

    /**
     * Queues a JSON response. Bodies given as arrays are encoded and served
     * with a JSON content type, which is what the API always sends.
     *
     * @param array<string, mixed>|string $body
     * @param array<string, string>       $headers
     */
    public function willRespond(int $status = 200, array|string $body = [], array $headers = []): self
    {
        $isJson = is_array($body);

        $this->queue[] = new Response(
            $status,
            array_merge($isJson ? ['Content-Type' => 'application/json'] : [], $headers),
            $isJson ? Json::encode($body) : $body
        );

        return $this;
    }

    /**
     * Queues a raw (non-JSON) response, e.g. a PDF download.
     *
     * @param array<string, string> $headers
     */
    public function willRespondRaw(int $status, string $body, array $headers = []): self
    {
        $this->queue[] = new Response($status, $headers, $body);

        return $this;
    }

    public function willThrow(Throwable $exception): self
    {
        $this->queue[] = $exception;

        return $this;
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        $next = array_shift($this->queue);

        if ($next === null) {
            throw new RuntimeException(sprintf(
                'MockHttpClient: no response queued for %s %s',
                $request->method,
                $request->uri
            ));
        }

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }

    public function callCount(): int
    {
        return count($this->requests);
    }

    public function lastRequest(): Request
    {
        $request = end($this->requests);

        if ($request === false) {
            throw new RuntimeException('MockHttpClient: no request was made.');
        }

        return $request;
    }

    public function requestAt(int $index): Request
    {
        if (! isset($this->requests[$index])) {
            throw new RuntimeException(sprintf('MockHttpClient: no request at index %d.', $index));
        }

        return $this->requests[$index];
    }

    /**
     * The decoded JSON body of a recorded request.
     *
     * @return array<string, mixed>
     */
    public function bodyAt(int $index): array
    {
        return Json::toArray(Json::decode($this->requestAt($index)->body ?? ''));
    }
}
