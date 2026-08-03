<?php

declare(strict_types=1);

namespace NotifyPress\Http\Client;

use NotifyPress\Exception\ConnectionException;
use NotifyPress\Exception\TimeoutException;
use NotifyPress\Http\HttpClientInterface;
use NotifyPress\Http\Request;
use NotifyPress\Http\Response;
use WP_Error;

/**
 * Routes requests through WordPress's own HTTP stack.
 *
 * This matters more than it looks: wp_remote_request() honours the site's
 * proxy constants, the http_request_args filters, WP_HTTP_BLOCK_EXTERNAL
 * allowlists, and the host's SSL configuration. A raw cURL call from inside
 * a plugin bypasses all of it and will break on managed hosts that require
 * an outbound proxy.
 */
final class WordPressClient implements HttpClientInterface
{
    public static function isSupported(): bool
    {
        return function_exists('wp_remote_request');
    }

    public function send(Request $request): Response
    {
        /** @var array<string, mixed>|WP_Error $result */
        $result = wp_remote_request($request->uri, [
            'method' => $request->method,
            'headers' => $request->headers,
            'body' => $request->body,
            'timeout' => $request->timeout,
            'redirection' => 0,
            'sslverify' => $request->verifySsl,
            'user-agent' => $request->headers['User-Agent'] ?? '',
        ]);

        if (is_wp_error($result)) {
            throw $this->transportFailure($result, $request);
        }

        /** @var int $status */
        $status = wp_remote_retrieve_response_code($result);
        /** @var string $body */
        $body = wp_remote_retrieve_body($result);

        return new Response($status, $this->headers($result), $body);
    }

    /**
     * WP_Error does not distinguish timeouts structurally, so the message is
     * the only signal available.
     */
    private function transportFailure(WP_Error $error, Request $request): ConnectionException|TimeoutException
    {
        $message = $error->get_error_message();

        if (stripos($message, 'timed out') !== false || stripos($message, 'cURL error 28') !== false) {
            return new TimeoutException(sprintf(
                'Request timed out after %.1fs: %s %s',
                $request->timeout,
                $request->method,
                $request->uri
            ));
        }

        return new ConnectionException(sprintf(
            'HTTP request failed (%s): %s',
            (string) $error->get_error_code(),
            $message
        ));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, string>
     */
    private function headers(array $result): array
    {
        $headers = wp_remote_retrieve_headers($result);

        // Modern WordPress returns a case-insensitive dictionary object here,
        // not a plain array — older versions returned an array.
        if (is_object($headers) && method_exists($headers, 'getAll')) {
            /** @var array<string, mixed> $headers */
            $headers = $headers->getAll();
        }

        if (! is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $name => $value) {
            // Repeated headers arrive as arrays; join them the way HTTP does.
            $normalized[(string) $name] = is_array($value)
                ? implode(', ', array_map('strval', $value))
                : (string) $value;
        }

        return $normalized;
    }
}
