<?php

declare(strict_types=1);

use NotifyPress\Exception\ValidationException;
use NotifyPress\Http\Multipart\MultipartBody;

it('encodes fields with CRLF line endings and a closing boundary', function () {
    $body = MultipartBody::make('BOUND')
        ->field('title', 'Flash sale')
        ->field('message', 'Ends tonight');

    expect($body->toString())->toBe(
        "--BOUND\r\n"
        . "Content-Disposition: form-data; name=\"title\"\r\n\r\n"
        . "Flash sale\r\n"
        . "--BOUND\r\n"
        . "Content-Disposition: form-data; name=\"message\"\r\n\r\n"
        . "Ends tonight\r\n"
        . "--BOUND--\r\n"
    );
});

it('advertises its own boundary in the content type', function () {
    expect(MultipartBody::make('BOUND')->contentType())
        ->toBe('multipart/form-data; boundary=BOUND');
});

it('generates a unique boundary when none is given', function () {
    $first = MultipartBody::make()->contentType();
    $second = MultipartBody::make()->contentType();

    expect($first)->toStartWith('multipart/form-data; boundary=----NotifyPress')
        ->and($first)->not->toBe($second);
});

it('attaches a file from raw bytes', function () {
    $body = MultipartBody::make('BOUND')->file('icon', 'RAWBYTES', 'logo.png');

    expect($body->toString())
        ->toContain('Content-Disposition: form-data; name="icon"; filename="logo.png"')
        ->toContain('Content-Type: image/png')
        ->toContain('RAWBYTES');
});

it('attaches a file from a path, defaulting the name to the basename', function () {
    $path = tempnam(sys_get_temp_dir(), 'np') . '.png';
    file_put_contents($path, 'FILEBYTES');

    $body = MultipartBody::make('BOUND')->file('image', $path);

    expect($body->toString())
        ->toContain('filename="' . basename($path) . '"')
        ->toContain('FILEBYTES');

    unlink($path);
});

it('attaches a file from a stream resource', function () {
    $stream = fopen('php://memory', 'r+');
    assert(is_resource($stream));
    fwrite($stream, 'STREAMBYTES');
    rewind($stream);

    $body = MultipartBody::make('BOUND')->file('icon', $stream, 'icon.webp');

    expect($body->toString())
        ->toContain('Content-Type: image/webp')
        ->toContain('STREAMBYTES');

    fclose($stream);
});

it('guesses the content type from the extension', function (string $filename, string $expected) {
    expect(MultipartBody::make('B')->file('icon', 'x', $filename)->toString())
        ->toContain('Content-Type: ' . $expected);
})->with([
    ['a.png', 'image/png'],
    ['a.jpg', 'image/jpeg'],
    ['a.jpeg', 'image/jpeg'],
    ['a.gif', 'image/gif'],
    ['a.webp', 'image/webp'],
]);

it('rejects a file type the server would reject anyway', function () {
    expect(fn () => MultipartBody::make()->file('icon', 'bytes', 'malware.exe'))
        ->toThrow(ValidationException::class);
});

it('reports an oversized upload against the field the API would blame', function (string $field, int $kilobytes) {
    $exception = null;

    try {
        MultipartBody::make()->file($field, str_repeat('x', ($kilobytes + 1) * 1024), $field . '.png');
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception?->fields())->toBe([$field])
        ->and($exception?->first($field))->toContain((string) $kilobytes);
})->with([
    'icon over 2 MB' => ['icon', MultipartBody::MAX_ICON_KB],
    'image over 4 MB' => ['image', MultipartBody::MAX_IMAGE_KB],
]);

it('accepts a file right at the limit', function () {
    $body = MultipartBody::make('B')->file('icon', str_repeat('x', MultipartBody::MAX_ICON_KB * 1024), 'icon.png');

    expect($body->isEmpty())->toBeFalse();
});

it('defers entirely to the server when client validation is waived', function () {
    $body = MultipartBody::make('B')->withoutClientValidation()->file('icon', str_repeat('x', 4096), 'huge.exe');

    expect($body->toString())->toContain('filename="huge.exe"');
});

it('stays immutable as it is built', function () {
    $empty = MultipartBody::make('B');
    $withField = $empty->field('title', 'Hi');

    expect($empty->isEmpty())->toBeTrue()
        ->and($withField->isEmpty())->toBeFalse();
});

it('seeds a body from an array of scalars, skipping anything else', function () {
    $body = MultipartBody::fromArray([
        'title' => 'Hi',
        'segment_id' => 4,
        'active' => true,
        'nested' => ['not' => 'scalar'],
    ]);

    expect($body->toString())
        ->toContain('name="title"')
        ->toContain('name="segment_id"')
        ->toContain('name="active"')
        ->not->toContain('name="nested"');
});
