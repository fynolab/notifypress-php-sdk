<?php

declare(strict_types=1);

namespace NotifyPress\Payload\Concerns;

/**
 * Shared plumbing for the fluent payload builders.
 */
trait BuildsPayload
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    /**
     * Sets an arbitrary field. Useful when the API gains a parameter before
     * this SDK models it.
     */
    public function set(string $key, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;

        return $clone;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function merge(array $attributes): static
    {
        $clone = clone $this;
        $clone->attributes = array_merge($clone->attributes, $attributes);

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            $this->attributes,
            static fn (mixed $value): bool => $value !== null
        );
    }
}
