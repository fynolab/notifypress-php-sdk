<?php

declare(strict_types=1);

namespace NotifyPress\Enum;

enum PromptStyle: string
{
    case MINIMAL = 'minimal';
    case CARD = 'card';
    case BANNER = 'banner';
}
