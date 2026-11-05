<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use NotifyPress\Enum\RecurringFrequency;
use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Body for POST /notifications/{id}/recurring-send.
 *
 * The named constructors exist because the API's rules are conditional —
 * `days` is required only for weekly, `day_of_month` only for monthly — and
 * a single generic builder makes it easy to send an invalid combination:
 *
 *     RecurringSchedule::daily('09:30');
 *     RecurringSchedule::weekly('09:30')->onDays([1, 3, 5])->inTimezone('Asia/Dhaka');
 *     RecurringSchedule::monthly('09:30')->onDayOfMonth(15);
 *
 * The time and weekdays are read in inTimezone() (an IANA name). Without it the API uses the
 * account's saved timezone. Each run keeps its local time across daylight saving changes.
 */
final class RecurringSchedule implements Payload
{
    use BuildsPayload;

    public const TIME_FORMAT = 'H:i';

    private function __construct(RecurringFrequency $frequency, string $time)
    {
        $this->attributes = [
            'frequency' => $frequency->value,
            'time' => $time,
        ];
    }

    /**
     * @param string $time Wall-clock time in "H:i" format, e.g. "09:30", in inTimezone().
     */
    public static function daily(string $time): self
    {
        return new self(RecurringFrequency::DAILY, $time);
    }

    public static function weekly(string $time): self
    {
        return new self(RecurringFrequency::WEEKLY, $time);
    }

    public static function monthly(string $time): self
    {
        return new self(RecurringFrequency::MONTHLY, $time);
    }

    /**
     * Weekdays to send on, 0 (Sunday) through 6 (Saturday). Weekly only.
     *
     * @param list<int> $days
     */
    public function onDays(array $days): static
    {
        return $this->set('days', array_values($days));
    }

    /**
     * Day of the month to send on, 1–31. Monthly only.
     */
    public function onDayOfMonth(int $day): static
    {
        return $this->set('day_of_month', $day);
    }

    public function time(string $time): static
    {
        return $this->set('time', $time);
    }

    /**
     * The IANA timezone the time and weekdays are in, e.g. "Asia/Dhaka" or "America/Toronto".
     */
    public function inTimezone(string $timezone): static
    {
        return $this->set('timezone', $timezone);
    }
}
