<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Body for POST /subscribers. Upserts on the FCM token.
 */
final class SubscriberCreate implements Payload
{
    use BuildsPayload;

    public function __construct(string $token)
    {
        $this->attributes = ['token' => $token];
    }

    public static function make(string $token): self
    {
        return new self($token);
    }

    /** Max 25 characters. */
    public function city(string $city): static
    {
        return $this->set('city', $city);
    }

    /** Max 25 characters. */
    public function country(string $country): static
    {
        return $this->set('country', $country);
    }

    /** Max 10 characters. */
    public function browser(string $browser): static
    {
        return $this->set('browser', $browser);
    }

    /** Max 25 characters. */
    public function device(string $device): static
    {
        return $this->set('device', $device);
    }

    /** Max 25 characters. */
    public function timezone(string $timezone): static
    {
        return $this->set('timezone', $timezone);
    }

    public function ip(string $ip): static
    {
        return $this->set('ip', $ip);
    }

    public function active(bool $active = true): static
    {
        return $this->set('active', $active);
    }

    public function inactive(): static
    {
        return $this->set('active', false);
    }
}
