<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use NotifyPress\Enum\SegmentCondition;
use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * An audience rule, used by segments and by the live preview-count endpoint.
 *
 *     SegmentRule::make()
 *         ->location(['US', 'CA'], SegmentCondition::IS_ONE_OF)
 *         ->device(['Desktop'])
 *         ->browser(['Chrome', 'Firefox'], SegmentCondition::IS_ONE_OF);
 */
final class SegmentRule implements Payload
{
    use BuildsPayload;

    public static function make(): self
    {
        return new self();
    }

    /**
     * @param list<string> $countries
     */
    public function location(array $countries, SegmentCondition $condition = SegmentCondition::IS_EQUAL_TO): static
    {
        return $this->group('location', $countries, $condition);
    }

    /**
     * @param list<string> $devices Desktop, Mobile, or Tablet.
     */
    public function device(array $devices, SegmentCondition $condition = SegmentCondition::IS_EQUAL_TO): static
    {
        return $this->group('device', $devices, $condition);
    }

    /**
     * @param list<string> $browsers
     */
    public function browser(array $browsers, SegmentCondition $condition = SegmentCondition::IS_EQUAL_TO): static
    {
        return $this->group('browser', $browsers, $condition);
    }

    /**
     * @param list<string> $values
     */
    private function group(string $key, array $values, SegmentCondition $condition): static
    {
        return $this->set($key, [
            'condition' => $condition->value,
            'values' => array_values($values),
        ]);
    }
}
