<?php

declare(strict_types=1);

namespace NotifyPress\Enum;

enum AutomationStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case ARCHIVED = 'archived';
}
