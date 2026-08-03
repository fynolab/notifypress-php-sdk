<?php

declare(strict_types=1);

namespace NotifyPress\Enum;

enum ReportType: string
{
    case CAMPAIGN_PERFORMANCE = 'campaign-performance';
    case SUBSCRIBER_GROWTH = 'subscriber-growth';
    case MONTHLY_SUMMARY = 'monthly-summary';
}
