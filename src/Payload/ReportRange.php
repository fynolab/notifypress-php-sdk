<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use DateTimeInterface;
use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Date range for the report-generation endpoints.
 *
 *     ReportRange::make()->from('2026-07-01')->to('2026-07-31');
 *     ReportRange::lastDays(30);
 */
final class ReportRange implements Payload
{
    use BuildsPayload;

    public const DATE_FORMAT = 'Y-m-d';

    public static function make(): self
    {
        return new self();
    }

    /**
     * A range covering the last N days, ending today.
     */
    public static function lastDays(int $days): self
    {
        $range = new self();

        return $range
            ->from(date(self::DATE_FORMAT, strtotime(sprintf('-%d days', $days))))
            ->to(date(self::DATE_FORMAT));
    }

    public function from(DateTimeInterface|string $start): static
    {
        return $this->set(
            'start_date',
            $start instanceof DateTimeInterface ? $start->format(self::DATE_FORMAT) : $start
        );
    }

    /** Must be on or after the start date. */
    public function to(DateTimeInterface|string $end): static
    {
        return $this->set(
            'end_date',
            $end instanceof DateTimeInterface ? $end->format(self::DATE_FORMAT) : $end
        );
    }
}
