<?php

declare(strict_types=1);

namespace NotifyPress\Payload;

use NotifyPress\Enum\PromptStyle;
use NotifyPress\Payload\Concerns\BuildsPayload;

/**
 * Body for PUT /sites/current and PUT /sites/{id}.
 *
 * site_name and site_url are both required by the server, so they are
 * constructor arguments rather than fluent setters.
 */
final class SiteUpdate implements Payload
{
    use BuildsPayload;

    public function __construct(string $siteName, string $siteUrl)
    {
        $this->attributes = [
            'site_name' => $siteName,
            'site_url' => $siteUrl,
        ];
    }

    public static function make(string $siteName, string $siteUrl): self
    {
        return new self($siteName, $siteUrl);
    }

    public function enableGeolocation(bool $enabled = true): static
    {
        return $this->set('enable_geolocation', $enabled);
    }

    public function removePoweredBy(bool $remove = true): static
    {
        return $this->set('remove_powered_by', $remove);
    }

    /** Max 120 characters. */
    public function promptTitle(string $title): static
    {
        return $this->set('prompt_title', $title);
    }

    /** Max 300 characters. */
    public function promptMessage(string $message): static
    {
        return $this->set('prompt_message', $message);
    }

    /** Max 500 characters. */
    public function promptIcon(string $url): static
    {
        return $this->set('prompt_icon', $url);
    }

    /** Both max 60 characters. */
    public function promptButtons(string $approveText, string $cancelText): static
    {
        return $this->set('prompt_approve_text', $approveText)
            ->set('prompt_cancel_text', $cancelText);
    }

    public function promptStyle(PromptStyle|string $style): static
    {
        return $this->set('prompt_style', $style instanceof PromptStyle ? $style->value : $style);
    }

    /** Max 20 characters, e.g. "#3858e9". */
    public function promptAccentColor(string $color): static
    {
        return $this->set('prompt_accent_color', $color);
    }

    /** How long to wait before showing the prompt, 0–60 seconds. */
    public function promptDelaySeconds(int $seconds): static
    {
        return $this->set('prompt_delay_seconds', $seconds);
    }

    /** How long a dismissal suppresses the prompt, 0–365 days. */
    public function promptHideDays(int $days): static
    {
        return $this->set('prompt_hide_days', $days);
    }

    public function welcomeNotification(
        bool $enabled,
        ?string $title = null,
        ?string $message = null,
        ?string $icon = null,
        ?string $url = null,
    ): static {
        return $this->set('welcome_notification_enabled', $enabled)
            ->set('welcome_notification_title', $title)
            ->set('welcome_notification_message', $message)
            ->set('welcome_notification_icon', $icon)
            ->set('welcome_notification_url', $url);
    }
}
