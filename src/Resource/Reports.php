<?php

declare(strict_types=1);

namespace NotifyPress\Resource;

use DateTimeInterface;
use NotifyPress\Enum\ReportType;
use NotifyPress\Http\RequestOptions;
use NotifyPress\Http\Response;
use NotifyPress\Model\ReportJob;
use NotifyPress\Payload\ReportRange;

/**
 * PDF report generation.
 *
 * The three generator endpoints require a paid subscription, are rate limited
 * to 10/min, and answer 202 with a signed `pdf_url` valid for 10 minutes.
 */
final class Reports extends Resource
{
    /**
     * GET /reports/meta — what can be reported on, the retention window, `isPaidUser`, and
     * `scheduledReportsEnabled` (whether weekly/monthly emailed reports are on for the site).
     *
     * @return array<string, mixed>
     */
    public function meta(?RequestOptions $options = null): array
    {
        return $this->transport->request('GET', '/reports/meta', [], null, $options);
    }

    /**
     * POST /reports/campaign-performance
     *
     * @param ReportRange|array<string, mixed> $range
     */
    public function campaignPerformance(
        ReportRange|array $range = [],
        ?RequestOptions $options = null,
    ): ReportJob {
        return $this->generate(ReportType::CAMPAIGN_PERFORMANCE, $range, $options);
    }

    /**
     * POST /reports/subscriber-growth
     *
     * @param ReportRange|array<string, mixed> $range
     */
    public function subscriberGrowth(
        ReportRange|array $range = [],
        ?RequestOptions $options = null,
    ): ReportJob {
        return $this->generate(ReportType::SUBSCRIBER_GROWTH, $range, $options);
    }

    /**
     * POST /reports/monthly-summary
     *
     * @param ReportRange|array<string, mixed> $range
     */
    public function monthlySummary(
        ReportRange|array $range = [],
        ?RequestOptions $options = null,
    ): ReportJob {
        return $this->generate(ReportType::MONTHLY_SUMMARY, $range, $options);
    }

    /**
     * GET /reports/download — the generated PDF itself.
     *
     * Returns the raw Response because the body is binary, not JSON. All four
     * parameters are required and must match the generation request exactly —
     * they are what identifies the stored file. Answers 404 until generation
     * has finished, so wait for the job before calling:
     *
     *     $client->reports
     *         ->download(ReportType::CAMPAIGN_PERFORMANCE, $siteId, '2026-07-01', '2026-07-31')
     *         ->saveTo('/tmp/report.pdf');
     *
     * @throws \NotifyPress\Exception\NotFoundException while the report is still generating
     */
    public function download(
        ReportType|string $type,
        int $siteId,
        DateTimeInterface|string $startDate,
        DateTimeInterface|string $endDate,
        ?RequestOptions $options = null,
    ): Response {
        return $this->transport->requestRaw('GET', '/reports/download', [
            'report_type' => $type instanceof ReportType ? $type->value : $type,
            'site_id' => $siteId,
            'start_date' => self::date($startDate),
            'end_date' => self::date($endDate),
        ], null, $options);
    }

    private static function date(DateTimeInterface|string $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format(ReportRange::DATE_FORMAT) : $date;
    }

    /**
     * @param ReportRange|array<string, mixed> $range
     */
    private function generate(
        ReportType $type,
        ReportRange|array $range,
        ?RequestOptions $options,
    ): ReportJob {
        return ReportJob::fromArray($this->transport->request(
            'POST',
            '/reports/' . $type->value,
            [],
            $this->body($range) ?? [],
            $options
        ));
    }
}
