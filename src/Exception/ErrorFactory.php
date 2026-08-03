<?php

declare(strict_types=1);

namespace NotifyPress\Exception;

use NotifyPress\Http\Response;
use NotifyPress\Support\Json;

/**
 * Turns a non-2xx response into the most specific exception available.
 *
 * The API produces three distinct error body shapes, and this is the single
 * place that knows about all of them:
 *
 *   1. Validation:        {"message": "...", "errors": {"field": ["..."]}}   (422)
 *   2. General:           {"message": "..."}  plus "upgrade_url" on paid gates
 *   3. Token verification: {"error": "..."}                              (401/403)
 */
final class ErrorFactory
{
    private function __construct()
    {
    }

    public static function create(Response $response): ApiException
    {
        $decoded = $response->isJson() ? Json::decode($response->body) : $response->body;
        $body = is_array($decoded) ? $decoded : null;
        $status = $response->status;
        $message = self::message($body, $status);

        if ($status === 422) {
            return new ValidationException($message, $status, $decoded, $response, self::errors($body));
        }

        if ($status === 401) {
            return new AuthenticationException($message, $status, $decoded, $response);
        }

        if ($status === 403) {
            // A paid-plan gate is the only 403 carrying an upgrade URL.
            if (isset($body['upgrade_url']) && is_string($body['upgrade_url'])) {
                return new PaidPlanException($message, $status, $decoded, $response, $body['upgrade_url']);
            }

            // Token verification reports failures under "error" rather than
            // "message" — that shape means the credential itself was rejected.
            if (isset($body['error']) && is_string($body['error'])) {
                return new AuthenticationException($message, $status, $decoded, $response);
            }

            return new ForbiddenException($message, $status, $decoded, $response);
        }

        if ($status === 404) {
            return new NotFoundException($message, $status, $decoded, $response);
        }

        if ($status === 429) {
            return new RateLimitException(
                $message,
                $status,
                $decoded,
                $response,
                self::retryAfter($response)
            );
        }

        if ($status >= 500) {
            return new ServerException($message, $status, $decoded, $response);
        }

        return new ApiException($message, $status, $decoded, $response);
    }

    /**
     * Seconds to wait before retrying. Retry-After may be an integer count of
     * seconds or an HTTP date; both forms are accepted.
     */
    public static function retryAfter(Response $response): ?int
    {
        $header = $response->header('retry-after');

        if ($header === null || trim($header) === '') {
            return null;
        }

        $header = trim($header);

        if (ctype_digit($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private static function message(?array $body, int $status): string
    {
        if (isset($body['message']) && is_string($body['message']) && $body['message'] !== '') {
            return $body['message'];
        }

        if (isset($body['error']) && is_string($body['error']) && $body['error'] !== '') {
            return $body['error'];
        }

        return sprintf('Request failed with status %d', $status);
    }

    /**
     * @param  array<string, mixed>|null   $body
     * @return array<string, list<string>>
     */
    private static function errors(?array $body): array
    {
        if (! isset($body['errors']) || ! is_array($body['errors'])) {
            return [];
        }

        $errors = [];

        foreach ($body['errors'] as $field => $messages) {
            if (! is_string($field)) {
                continue;
            }

            $errors[$field] = array_values(array_filter(
                is_array($messages) ? $messages : [$messages],
                'is_string'
            ));
        }

        return $errors;
    }
}
