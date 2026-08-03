<?php

declare(strict_types=1);

namespace NotifyPress\Enum;

enum AutomationStepType: string
{
    case WAIT = 'wait';
    case SEND_NOTIFICATION = 'send_notification';
    case ADD_TAG = 'add_tag';
    case WEBHOOK = 'webhook';

    /**
     * Defined server-side but not yet executable — an automation using one of
     * these cannot be activated.
     */
    case CONDITION = 'condition';
    case AB_SPLIT = 'ab_split';

    public function isSupported(): bool
    {
        return ! in_array($this, [self::CONDITION, self::AB_SPLIT], true);
    }
}
