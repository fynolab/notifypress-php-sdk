<?php

declare(strict_types=1);

namespace NotifyPress\Enum;

enum RecurringFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
}
