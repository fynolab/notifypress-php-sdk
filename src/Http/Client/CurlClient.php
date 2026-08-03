<?php

declare(strict_types=1);

namespace NotifyPress\Http\Client;

use CurlHandle;
use NotifyPress\Exception\ConnectionException;
use NotifyPress\Exception\TimeoutException;
use NotifyPress\Http\HttpClientInterface;
use NotifyPress\Http\Request;
use NotifyPress\Http\Response;

/**
 * The default client: ext-curl only, no Composer dependencies.
 */
final class CurlClient implements HttpClientInterface
{
    public static function isSupported(): bool
    {
        return extension_loaded('curl');
    }

    public function send(Request $request): Response
    {
        $handle = curl_init();

        if ($handle === false) {
            throw new ConnectionException('Could not initialise a cURL handle.');
        }

        $responseHeaders = [];

        curl_setopt_array($handle, [
            CURLOPT_URL => $request->uri,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->formatHeaders($request->headers),
            CURLOPT_TIMEOUT_MS => (int) round($request->timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($request->connectTimeout * 1000),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => $request->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $request->verifySsl ? 2 : 0,
            // Advertise every encoding cURL can decode, transparently.
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => function ($_handle, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        if ($request->body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $request->body);
        }

        try {
            $body = curl_exec($handle);
            $errno = curl_errno($handle);

            if ($errno !== 0) {
                $error = curl_error($handle);

                if ($errno === CURLE_OPERATION_TIMEOUTED) {
                    throw new TimeoutException(sprintf(
                        'Request timed out after %.1fs: %s %s',
                        $request->timeout,
                        $request->method,
                        $request->uri
                    ));
                }

                throw new ConnectionException(sprintf(
                    'HTTP request failed (cURL %d): %s',
                    $errno,
                    $error
                ));
            }

            /** @var int $status */
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

            return new Response($status, $responseHeaders, is_string($body) ? $body : '');
        } finally {
            $this->close($handle);
        }
    }

    /**
     * @param array<string, string> $headers
     *
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        return $formatted;
    }

    private function close(CurlHandle $handle): void
    {
        curl_close($handle);
    }
}
