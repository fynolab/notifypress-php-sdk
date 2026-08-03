<?php

declare(strict_types=1);

namespace NotifyPress\Enum;

enum NotificationPurpose: string
{
    case CAMPAIGN = 'campaign';
    case AUTOMATION_MESSAGE = 'automation_message';
    case WELCOME_MESSAGE = 'welcome_message';
}
