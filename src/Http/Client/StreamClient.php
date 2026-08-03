<?php

declare(strict_types=1);

namespace NotifyPress\Http\Client;

use NotifyPress\Exception\ConnectionException;
use NotifyPress\Exception\TimeoutException;
use NotifyPress\Http\HttpClientInterface;
use NotifyPress\Http\Request;
use NotifyPress\Http\Response;

/**
 * Fallback for hosts without ext-curl, using PHP stream wrappers.
 */
final class StreamClient implements HttpClientInterface
{
    public static function isSupported(): bool
    {
        return filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL);
    }

    public function send(Request $request): Response
    {
        $headerLines = [];

        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $request->method,
                'header' => implode("\r\n", $headerLines),
                'content' => $request->body ?? '',
                'timeout' => $request->timeout,
                'follow_location' => 0,
                // Without this, PHP returns false for 4xx/5xx and the body is
                // lost — but the body is exactly where the API puts its errors.
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => $request->verifySsl,
                'verify_peer_name' => $request->verifySsl,
            ],
        ]);

        $http_response_header = [];
        $body = @file_get_contents($request->uri, false, $context);

        if ($body === false) {
            $error = error_get_last();
            $message = $error['message'] ?? 'unknown error';

            if (stripos($message, 'timed out') !== false) {
                throw new TimeoutException(sprintf(
                    'Request timed out after %.1fs: %s %s',
                    $request->timeout,
                    $request->method,
                    $request->uri
                ));
            }

            throw new ConnectionException(sprintf('HTTP request failed: %s', $message));
        }

        return $this->parse($http_response_header, $body);
    }

    /**
     * @param list<string> $rawHeaders
     */
    private function parse(array $rawHeaders, string $body): Response
    {
        $status = 0;
        $headers = [];

        foreach ($rawHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
                // A redirect chain emits several status lines; the last wins.
                $status = (int) $matches[1];
                $headers = [];

                continue;
            }

            $parts = explode(':', $line, 2);

            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }

        return new Response($status, $headers, $body);
    }
}
