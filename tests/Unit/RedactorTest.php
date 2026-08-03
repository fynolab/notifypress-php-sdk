<?php

declare(strict_types=1);

use NotifyPress\Http\Redactor;

it('redacts the Authorization header but keeps the scheme', function () {
    // The WordPress plugin used to log this header verbatim into debug.log.
    expect(Redactor::headers(['Authorization' => 'Bearer 1|super-secret-value']))
        ->toBe(['Authorization' => 'Bearer ***']);
});

it('redacts a non-Bearer credential entirely', function () {
    expect(Redactor::headers(['Authorization' => 'Basic dXNlcjpwYXNz']))
        ->toBe(['Authorization' => '***']);
});

it('redacts the subscription token header', function () {
    expect(Redactor::headers(['notifypress-subscription-token' => 'fcm-abc']))
        ->toBe(['notifypress-subscription-token' => '***']);
});

it('matches secret headers whatever their casing', function (string $name) {
    expect(Redactor::headers([$name => 'secret-value'])[$name])->toBe('***');
})->with([
    'AUTHORIZATION' => ['AUTHORIZATION'],
    'NotifyPress-Subscription-Token' => ['NotifyPress-Subscription-Token'],
    'X-Api-Key' => ['X-Api-Key'],
    'Cookie' => ['Cookie'],
    'Set-Cookie' => ['Set-Cookie'],
]);

it('redacts anything whose name suggests a credential', function (string $name) {
    expect(Redactor::headers([$name => 'value'])[$name])->toBe('***');
})->with([
    ['X-Site-Token'],
    ['X-Client-Secret'],
    ['X-Public-Key'],
]);

it('leaves ordinary headers alone', function () {
    expect(Redactor::headers([
        'Accept' => 'application/json',
        'User-Agent' => 'notifypress-php/0.1.0',
        'Content-Type' => 'application/json',
    ]))->toBe([
        'Accept' => 'application/json',
        'User-Agent' => 'notifypress-php/0.1.0',
        'Content-Type' => 'application/json',
    ]);
});

it('strips a known secret out of arbitrary text', function () {
    $text = 'Request failed: token 1|super-secret-value was rejected.';

    expect(Redactor::text($text, '1|super-secret-value'))
        ->toBe('Request failed: token *** was rejected.');
});

it('ignores a secret too short to be one, rather than mangling the text', function (?string $secret) {
    $text = 'Nothing sensitive at all.';

    expect(Redactor::text($text, $secret))->toBe($text);
})->with([
    'null' => [null],
    'too short' => ['abc'],
]);
