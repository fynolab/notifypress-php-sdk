<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Body for POST /segments and PUT /segments/{id}.
 */
final class SegmentPayload implements Payload
{
    use BuildsPayload;

    public function __construct(string $name)
    {
        $this->attributes = [
            'name' => $name,
            'is_active' => true,
        ];
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /** Max 255 characters. */
    public function description(string $description): static
    {
        return $this->set('description', $description);
    }

    public function active(bool $active = true): static
    {
        return $this->set('is_active', $active);
    }

    public function inactive(): static
    {
        return $this->set('is_active', false);
    }

    /**
     * @param SegmentRule|array<string, mixed> $rule
     */
    public function rule(SegmentRule|array $rule): static
    {
        return $this->set('rule', $rule instanceof SegmentRule ? $rule->toArray() : $rule);
    }
}
