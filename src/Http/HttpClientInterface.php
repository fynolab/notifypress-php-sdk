<?php

declare(strict_types=1);

namespace NotifyPress\Http;

use NotifyPress\Exception\ConnectionException;
use NotifyPress\Exception\TimeoutException;

/**
 * The SDK's HTTP seam.
 *
 * Implementations must return a Response for every HTTP status, including
 * 4xx and 5xx — turning those into exceptions is the Transport's job, not
 * the client's. Only genuine transport failures throw.
 */
interface HttpClientInterface
{
    /**
     * @throws TimeoutException    when the configured timeout elapses
     * @throws ConnectionException when no response could be obtained
     */
    public function send(Request $request): Response;
}
