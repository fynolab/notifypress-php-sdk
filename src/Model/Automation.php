<?php

declare(strict_types=1);

namespace NotifyPress\Model;

use NotifyPress\Enum\AutomationStatus;
use NotifyPress\Enum\AutomationTrigger;

final class Automation extends Model
{
    /**
     * @param list<array<string, mixed>> $steps
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        /** Raw strings rather than enums — see triggerEnum()/statusEnum(). */
        public readonly ?string $triggerType,
        public readonly ?string $status,
        public readonly array $steps,
        public readonly ?int $enrollmentsCount,
        public readonly ?int $activeCount,
        public readonly ?int $finishedCount,
        public readonly ?string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $steps = [];
        $rawSteps = $data['steps'] ?? null;

        if (is_array($rawSteps)) {
            foreach ($rawSteps as $step) {
                if (is_array($step)) {
                    /** @var array<string, mixed> $step */
                    $steps[] = $step;
                }
            }
        }

        return new self(
            self::int($data, 'id') ?? 0,
            self::str($data, 'name') ?? '',
            self::str($data, 'description'),
            self::str($data, 'trigger_type'),
            self::str($data, 'status'),
            $steps,
            self::int($data, 'enrollments_count'),
            self::int($data, 'active_count'),
            self::int($data, 'finished_count'),
            self::str($data, 'created_at'),
            $data,
        );
    }

    public function statusEnum(): ?AutomationStatus
    {
        return $this->status === null ? null : AutomationStatus::tryFrom($this->status);
    }

    public function triggerEnum(): ?AutomationTrigger
    {
        return $this->triggerType === null ? null : AutomationTrigger::tryFrom($this->triggerType);
    }

    public function isActive(): bool
    {
        return $this->status === AutomationStatus::ACTIVE->value;
    }
}
