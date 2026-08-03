<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use NotifyPress\Enum\TagType;
use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Body for POST /tags. A site may hold up to 200 tags.
 */
final class TagCreate implements Payload
{
    use BuildsPayload;

    public function __construct(string $name)
    {
        $this->attributes = ['name' => $name];
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public static function category(string $name): self
    {
        return (new self($name))->type(TagType::CATEGORY);
    }

    public static function interest(string $name): self
    {
        return (new self($name))->type(TagType::INTEREST);
    }

    public static function custom(string $name): self
    {
        return (new self($name))->type(TagType::CUSTOM);
    }

    public function type(TagType|string $type): static
    {
        return $this->set('type', $type instanceof TagType ? $type->value : $type);
    }
}
