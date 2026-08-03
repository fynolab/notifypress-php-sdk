<?php

declare(strict_types=1);

namespace NotifyPress\Model;

use NotifyPress\Enum\PromptStyle;

final class Site extends Model
{
    public function __construct(
        public readonly int $id,
        public readonly bool $isActive,
        public readonly ?string $siteName,
        public readonly ?string $siteUrl,
        /** The npk_ key embedded in the site's pages. Not a secret. */
        public readonly string $publicKey,
        public readonly bool $enableGeolocation,
        public readonly bool $removePoweredBy,
        public readonly ?string $promptTitle,
        public readonly ?string $promptMessage,
        public readonly ?string $promptIcon,
        public readonly ?string $promptApproveText,
        public readonly ?string $promptCancelText,
        /** Raw string rather than an enum — see promptStyleEnum(). */
        public readonly ?string $promptStyle,
        public readonly ?string $promptAccentColor,
        public readonly ?int $promptDelaySeconds,
        public readonly ?int $promptHideDays,
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
            self::int($data, 'id') ?? 0,
            self::bool($data, 'is_active'),
            self::str($data, 'site_name'),
            self::str($data, 'site_url'),
            self::str($data, 'public_key') ?? '',
            self::bool($data, 'enable_geolocation'),
            self::bool($data, 'remove_powered_by'),
            self::str($data, 'prompt_title'),
            self::str($data, 'prompt_message'),
            self::str($data, 'prompt_icon'),
            self::str($data, 'prompt_approve_text'),
            self::str($data, 'prompt_cancel_text'),
            self::str($data, 'prompt_style'),
            self::str($data, 'prompt_accent_color'),
            self::int($data, 'prompt_delay_seconds'),
            self::int($data, 'prompt_hide_days'),
            $data,
        );
    }

    /**
     * Null when the API returns a style this SDK release does not know about.
     */
    public function promptStyleEnum(): ?PromptStyle
    {
        return $this->promptStyle === null ? null : PromptStyle::tryFrom($this->promptStyle);
    }
}
