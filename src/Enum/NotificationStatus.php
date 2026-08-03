<?php

declare(strict_types=1);

namespace NotifyPress\Enum;

enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case SCHEDULED = 'scheduled';
    case RECURRING = 'recurring';
}
