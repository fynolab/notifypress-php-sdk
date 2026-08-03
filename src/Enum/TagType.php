<?php

declare(strict_types=1);

namespace NotifyPress\Enum;

enum TagType: string
{
    case CATEGORY = 'category';
    case INTEREST = 'interest';
    case CUSTOM = 'custom';
}
