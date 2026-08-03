<?php

declare(strict_types=1);

namespace NotifyPress\Model;

/**
 * A queued PDF report.
 */
final class ReportJob extends Model
{
    public function __construct(
        public readonly string $message,
        public readonly ?string $reportType,
        public readonly ?string $retentionMessage,
        /** Signed download URL, valid for 10 minutes. */
        public readonly ?string $pdfUrl,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::str($data, 'message') ?? '',
            self::str($data, 'report_type'),
            self::str($data, 'retention_message'),
            self::str($data, 'pdf_url'),
            $data,
        );
    }
}
