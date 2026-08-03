<?php

declare(strict_types=1);

namespace NotifyPress\Http\Multipart;

use NotifyPress\Exception\ValidationException;

/**
 * Builds a multipart/form-data body by hand.
 *
 * Hand-rolling the bytes means one code path works across every transport —
 * cURL, WordPress, and PSR-18 — instead of needing CURLFile for one, a
 * different array shape for another, and a PSR-7 UploadedFile for the third.
 *
 *     MultipartBody::make()
 *         ->field('title', 'Flash sale')
 *         ->field('message', 'Ends tonight')
 *         ->file('icon', '/path/to/icon.png');
 */
final class MultipartBody
{
    /** Server-side limit on the notification icon, in kilobytes. */
    public const MAX_ICON_KB = 2048;

    /** Server-side limit on the notification hero image, in kilobytes. */
    public const MAX_IMAGE_KB = 4096;

    private const ALLOWED_IMAGE_EXTENSIONS = ['jpeg', 'jpg', 'png', 'gif', 'webp'];

    /** Conservative floor across platforms (macOS caps paths at 1024). */
    private const MAX_PATH_LENGTH = 1024;

    private readonly string $boundary;

    /** @var list<array{name: string, value: string}> */
    private array $fields = [];

    /** @var list<array{name: string, filename: string, contentType: string, contents: string}> */
    private array $files = [];

    private bool $validate = true;

    public function __construct(?string $boundary = null)
    {
        $this->boundary = $boundary ?? '----NotifyPress' . bin2hex(random_bytes(16));
    }

    public static function make(?string $boundary = null): self
    {
        return new self($boundary);
    }

    /**
     * Seeds the body from an array of scalar fields.
     *
     * @param array<string, mixed> $fields
     */
    public static function fromArray(array $fields): self
    {
        $body = new self();

        foreach ($fields as $name => $value) {
            if (is_scalar($value)) {
                $body = $body->field($name, (string) $value);
            }
        }

        return $body;
    }

    public function field(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->fields[] = ['name' => $name, 'value' => $value];

        return $clone;
    }

    /**
     * Attaches a file from a path, a stream resource, or raw bytes.
     *
     * @param string|resource $file
     */
    public function file(
        string $name,
        mixed $file,
        ?string $filename = null,
        ?string $contentType = null,
    ): self {
        [$contents, $resolvedName] = $this->read($file, $filename);

        $clone = clone $this;
        $clone->files[] = [
            'name' => $name,
            'filename' => $resolvedName,
            'contentType' => $contentType ?? $this->guessContentType($resolvedName),
            'contents' => $contents,
        ];

        if ($clone->validate) {
            $clone->assertAcceptable($name, $resolvedName, strlen($contents));
        }

        return $clone;
    }

    /**
     * Skips the client-side size and extension checks, deferring entirely to
     * the server. Useful if the limits change server-side before this SDK
     * catches up.
     */
    public function withoutClientValidation(): self
    {
        $clone = clone $this;
        $clone->validate = false;

        return $clone;
    }

    public function contentType(): string
    {
        return 'multipart/form-data; boundary=' . $this->boundary;
    }

    public function toString(): string
    {
        $parts = '';

        foreach ($this->fields as $field) {
            $parts .= "--{$this->boundary}\r\n";
            $parts .= "Content-Disposition: form-data; name=\"{$field['name']}\"\r\n\r\n";
            $parts .= $field['value'] . "\r\n";
        }

        foreach ($this->files as $file) {
            $parts .= "--{$this->boundary}\r\n";
            $parts .= sprintf(
                "Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\n",
                $file['name'],
                $file['filename']
            );
            $parts .= "Content-Type: {$file['contentType']}\r\n\r\n";
            $parts .= $file['contents'] . "\r\n";
        }

        return $parts . "--{$this->boundary}--\r\n";
    }

    public function isEmpty(): bool
    {
        return $this->fields === [] && $this->files === [];
    }

    /**
     * @param  string|resource      $file
     * @return array{0: string, 1: string}
     */
    private function read(mixed $file, ?string $filename): array
    {
        if (is_resource($file)) {
            $contents = stream_get_contents($file);

            return [$contents === false ? '' : $contents, $filename ?? 'upload'];
        }

        if (is_string($file) && $this->looksLikePath($file) && is_file($file)) {
            $contents = file_get_contents($file);

            return [$contents === false ? '' : $contents, $filename ?? basename($file)];
        }

        // Raw bytes.
        return [is_string($file) ? $file : '', $filename ?? 'upload'];
    }

    /**
     * Whether a string is worth passing to is_file().
     *
     * Image bytes are also strings, and handing megabytes of them to a
     * filesystem call raises a path-length warning on every upload.
     */
    private function looksLikePath(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= self::MAX_PATH_LENGTH
            && ! str_contains($value, "\0")
            && ! str_contains($value, "\n");
    }

    /**
     * Mirrors the server's validation so oversized uploads fail locally with
     * the same field key the API would have used — one error path for callers.
     */
    private function assertAcceptable(string $field, string $filename, int $bytes): void
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension !== '' && ! in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
            throw new ValidationException(
                sprintf('The %s must be a file of type: %s.', $field, implode(', ', self::ALLOWED_IMAGE_EXTENSIONS)),
                422,
                null,
                null,
                [$field => [sprintf('The %s must be an image.', $field)]]
            );
        }

        $limitKb = match ($field) {
            'icon' => self::MAX_ICON_KB,
            'image' => self::MAX_IMAGE_KB,
            default => null,
        };

        if ($limitKb !== null && $bytes > $limitKb * 1024) {
            throw new ValidationException(
                sprintf('The %s may not be greater than %d kilobytes.', $field, $limitKb),
                422,
                null,
                null,
                [$field => [sprintf('The %s may not be greater than %d kilobytes.', $field, $limitKb)]]
            );
        }
    }

    private function guessContentType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
