<?php

declare(strict_types=1);

namespace NotifyPress\Enum;

enum AutomationTrigger: string
{
    case SUBSCRIBER_CREATED = 'subscriber_created';
    case CUSTOM_EVENT = 'custom_event';
}
