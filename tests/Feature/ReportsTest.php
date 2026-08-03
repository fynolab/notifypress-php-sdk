<?php

declare(strict_types=1);

use NotifyPress\Enum\ReportType;
use NotifyPress\Exception\NotFoundException;
use NotifyPress\Exception\PaidPlanException;
use NotifyPress\Payload\ReportRange;
use NotifyPress\Tests\Support\MockHttpClient;

it('queues each report type at its own endpoint', function (string $method, string $path) {
    $http = MockHttpClient::make()->willRespond(202, [
        'message' => 'Report queued.',
        'report_type' => $path,
        'pdf_url' => 'https://app.notifypress.com/signed/report.pdf',
    ]);

    $reports = testClient($http)->reports;

    $call = match ($method) {
        'campaignPerformance' => $reports->campaignPerformance(...),
        'subscriberGrowth' => $reports->subscriberGrowth(...),
        default => $reports->monthlySummary(...),
    };

    $job = $call(ReportRange::make()->from('2026-07-01')->to('2026-07-31'));

    expect($http->lastRequest()->method)->toBe('POST')
        ->and($http->lastRequest()->uri)->toBe('https://api.test/api/v1/reports/' . $path)
        ->and($http->bodyAt(0))->toBe(['start_date' => '2026-07-01', 'end_date' => '2026-07-31'])
        ->and($job->pdfUrl)->toBe('https://app.notifypress.com/signed/report.pdf')
        ->and($job->reportType)->toBe($path);
})->with([
    ['campaignPerformance', 'campaign-performance'],
    ['subscriberGrowth', 'subscriber-growth'],
    ['monthlySummary', 'monthly-summary'],
]);

it('formats a date range from DateTime values', function () {
    $range = ReportRange::make()
        ->from(new DateTimeImmutable('2026-07-01 13:45:00'))
        ->to(new DateTimeImmutable('2026-07-31 08:00:00'));

    expect($range->toArray())->toBe(['start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
});

it('builds a trailing-window range', function () {
    $range = ReportRange::lastDays(30)->toArray();

    expect($range['end_date'])->toBe(date('Y-m-d'))
        ->and($range['start_date'])->toBe(date('Y-m-d', strtotime('-30 days')));
});

it('sends an empty body when no range is given', function () {
    $http = MockHttpClient::make()->willRespond(202, ['message' => 'Report queued.']);

    testClient($http)->reports->campaignPerformance();

    expect($http->bodyAt(0))->toBe([]);
});

it('downloads the generated PDF as raw bytes', function () {
    $http = MockHttpClient::make()->willRespondRaw(
        200,
        '%PDF-1.4 report',
        ['Content-Type' => 'application/pdf']
    );

    $response = testClient($http)->reports->download(
        ReportType::CAMPAIGN_PERFORMANCE,
        3,
        '2026-07-01',
        new DateTimeImmutable('2026-07-31')
    );

    expect($response->body)->toBe('%PDF-1.4 report')
        ->and(queryOf($http->lastRequest()->uri))->toBe([
            'report_type' => 'campaign-performance',
            'site_id' => '3',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);
});

it('reports a report that is still generating as not found', function () {
    $http = MockHttpClient::make()->willRespond(404, ['message' => 'Not found.']);

    expect(fn () => testClient($http)->reports->download(ReportType::MONTHLY_SUMMARY, 3, '2026-07-01', '2026-07-31'))
        ->toThrow(NotFoundException::class);
});

it('reports a free plan as a paid-plan error', function () {
    $http = MockHttpClient::make()->willRespond(403, [
        'message' => 'Reports require a paid plan.',
        'upgrade_url' => 'https://app.notifypress.com/billing',
    ]);

    expect(fn () => testClient($http)->reports->subscriberGrowth())->toThrow(PaidPlanException::class);
});

it('reads the report metadata', function () {
    $http = MockHttpClient::make()->willRespond(200, ['retention_days' => 90, 'sites' => []]);

    expect(testClient($http)->reports->meta())->toBe(['retention_days' => 90, 'sites' => []]);
});

it('marks announcements read', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['ok' => true])
        ->willRespond(200, ['ok' => true]);

    $client = testClient($http);
    $client->announcements->markAllRead();
    $client->announcements->markRead(4);

    expect($http->requestAt(0)->uri)->toBe('https://api.test/api/v1/announcements/read-all')
        ->and($http->requestAt(1)->uri)->toBe('https://api.test/api/v1/announcements/4/read');
});

it('unwraps the dashboard and analytics envelopes', function () {
    $http = MockHttpClient::make()
        ->willRespond(200, ['data' => ['total_subscribers' => '1.2K']])
        ->willRespond(200, ['data' => ['click_through_rate' => 12], 'isPaidUser' => true]);

    $client = testClient($http);

    expect($client->dashboard->get())->toBe(['total_subscribers' => '1.2K'])
        ->and($client->dashboard->analytics())->toBe(['click_through_rate' => 12]);
});

it('returns the whole payload when the dashboard has no data envelope', function () {
    $http = MockHttpClient::make()->willRespond(200, ['total_subscribers' => '1.2K']);

    expect(testClient($http)->dashboard->get())->toBe(['total_subscribers' => '1.2K']);
});
