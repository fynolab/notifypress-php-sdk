<?php

declare(strict_types=1);

namespace NotifyPress\Enum;

enum SegmentCondition: string
{
    case IS_EQUAL_TO = 'is_equal_to';
    case IS_NOT_EQUAL_TO = 'is_not_equal_to';
    case IS_ONE_OF = 'is_one_of';
}
