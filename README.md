# NotifyPress PHP SDK

Official PHP client for the [NotifyPress](https://notifypress.com) REST API — browser push notifications, subscribers, segments, and automation workflows.

Runs unchanged in **WordPress**, **Laravel**, and **plain PHP**, with **no required runtime dependencies**.

```php
use NotifyPress\NotifyPress;
use NotifyPress\Payload\SendNotification;

$client = new NotifyPress(getenv('NOTIFYPRESS_API_TOKEN'));

$client->notifications->send(
    SendNotification::make('Flash sale', '25% off, today only')
        ->targetUrl('https://example.com/sale')
);
```

## Requirements

- PHP 8.1+
- `ext-json`, plus either `ext-curl` or `allow_url_fopen` (any PSR-18 client works too)

## Installation

```bash
composer require notifypress/sdk
```

## Credentials

NotifyPress issues two credentials per site, and they are not interchangeable:

| | Where to find it | Where it may be used |
|---|---|---|
| **API token** | Site settings → API token | Servers only. It is a secret with full access to the site. |
| **Public key** (`npk_...`) | Site settings → widget snippet | Safe to embed in a page. Used only by the public opt-in endpoints. |

Use `NotifyPress` with the API token, and `NotifyPressPublic` with the public key.

> **Pass the token exactly as the dashboard shows it.** If you have a value containing
> a pipe, use only the part *after* the pipe. The part before it is an internal id the
> server supplies itself; including it
> still authenticates, so nothing looks wrong, but the site can no longer be resolved
> and every call fails with `NotFoundException: No site is linked to this API token.`

## Quickstart per environment

### Plain PHP

Nothing to configure. The SDK falls back to cURL, then PHP streams.

```php
require 'vendor/autoload.php';

$client = new NotifyPress\NotifyPress('your-api-token');

foreach ($client->subscribers->list() as $subscriber) {
    echo $subscriber->token, PHP_EOL;
}
```

### Laravel

Guzzle is already installed, so the SDK detects it and reuses the application's HTTP stack. Bind it once:

```php
// app/Providers/AppServiceProvider.php
use NotifyPress\NotifyPress;

$this->app->singleton(NotifyPress::class, fn () => new NotifyPress(config('services.notifypress.token')));
```

```php
public function send(NotifyPress $notifypress): RedirectResponse
{
    $notifypress->notifications->send(
        SendNotification::make($request->title, $request->message)
    );

    return back();
}
```

### WordPress

Calls are routed through `wp_remote_request()` automatically, so the site's proxy constants, `http_request_args` filters, and `WP_HTTP_BLOCK_EXTERNAL` allowlist are all honoured — a raw cURL call from a plugin bypasses every one of them and breaks on managed hosts requiring an outbound proxy.

```php
require_once __DIR__ . '/vendor/autoload.php';

$client = new NotifyPress\NotifyPress(get_option('notifypress_api_token'));
$site = $client->sites->current();
```

### Anything else

Pass your own PSR-18 client:

```php
use NotifyPress\Config;
use NotifyPress\Http\Client\Psr18Client;

$client = new NotifyPress(
    Config::make('your-api-token')->withHttpClient(new Psr18Client($yourPsr18Client))
);
```

## Configuration

`Config` is immutable — every `with*()` returns a new instance:

```php
use NotifyPress\Config;

$client = new NotifyPress(
    Config::make('your-api-token')
        ->withBaseUrl('https://app.notifypress.com/api/v1')
        ->withTimeout(10.0)
        ->withMaxRetries(4)
        ->withHeader('X-Request-Source', 'my-app')
);
```

A built client can be re-pointed without rebuilding it: `$client->withBaseUrl(...)`, `->withApiToken(...)`, `->withTimeout(...)`.

## Resources

`sites`, `notifications`, `subscribers`, `tags`, `segments`, `automations`, `reports`, `announcements`, `dashboard`.

```php
$client->sites->current();
$client->sites->updateCurrent(SiteUpdate::make('Example', 'https://example.com')->promptDelaySeconds(5));

$client->notifications->list(NotificationQuery::make()->status(NotificationStatus::SENT)->limit(50));
$client->notifications->list(NotificationQuery::make()->fullText()->excludeWelcome()); // untruncated campaigns only
$client->notifications->retrieve(9);
$client->notifications->cancel(9);
$client->notifications->capabilities();   // ['canSchedule' => true, 'canRecur' => false] for the plan

$client->subscribers->create(SubscriberCreate::make($fcmToken)->country('CA')->browser('Chrome'));
$client->subscribers->addTag($fcmToken, $tagId);   // paid plans only (PaidPlanException on Free)
$client->subscribers->delete($subscriberId);   // numeric ID, not the token

$client->tags->create(TagCreate::interest('Deals'));   // paid plans only (PaidPlanException on Free)
$client->segments->list(SegmentQuery::make()->perPage(100));   // CursorPage; ->subscriberCount on each row
$client->segments->previewCount(SegmentRule::make()->location(['CA', 'US']));
$client->automations->list(AutomationQuery::make()->status(AutomationStatus::ACTIVE)->search('welcome'));
$client->automations->trigger('cart_abandoned', $fcmToken);
$client->dashboard->analytics();
```

## Fluent payloads

Every payload and query is an immutable builder, so a base can be safely specialised:

```php
$base = SendNotification::make('Flash sale', '25% off');

$client->notifications->send($base->toSegment(4));
$client->notifications->send($base->toEveryone()->iconUrl('https://example.com/icon.png'));
```

Plain arrays work everywhere a builder does, and `->set('key', $value)` passes through any parameter the SDK does not model yet:

```php
$client->notifications->send(['title' => 'Flash sale', 'message' => '25% off']);
$client->notifications->send($base->set('ttl_seconds', 300));
```

## Pagination

Iterating a list yields **the current page**. Walking the whole set is explicit, because every step is another HTTP round trip:

```php
$page = $client->subscribers->list(SubscriberQuery::make()->limit(100)->sortByCreatedAt());

$page->total;          // cached count of the full set
$page->active;         // cached count of active subscribers
$page->isPaidUser();   // tagging is a paid feature
$page->hasNextPage();
$page->nextPage();

foreach ($page as $subscriber) { }               // this page
foreach ($page->autoPaging() as $subscriber) { } // every page, fetched lazily
```

Query options (sorting, `per_page`) are kept on every page `nextPage()` and `autoPaging()` fetch. `segments->list()` is cursor-paginated the same way: 10 per page by default, up to 100 with `SegmentQuery::perPage()`.

`GET /automations` is offset-paginated and returns an `OffsetPage` with `currentPage`, `lastPage`, `isPaidUser()`, and `statusCounts()` (`all`, `active`, `paused`, `inactive` for the whole site).

## Errors

Every non-2xx response throws. The three server error shapes are normalised into one hierarchy:

```
NotifyPressException
├── ApiException            {status, body, response}
│   ├── ValidationException {errors}       422
│   ├── AuthenticationException            401, or 403 rejecting the credential
│   ├── PaidPlanException   {upgradeUrl}   403 on a paid-plan gate
│   ├── ForbiddenException                 other 403
│   ├── NotFoundException                  404
│   ├── RateLimitException  {retryAfter}   429
│   └── ServerException                    5xx
├── TimeoutException
├── ConnectionException
└── ConfigurationException
```

```php
use NotifyPress\Exception\PaidPlanException;
use NotifyPress\Exception\RateLimitException;
use NotifyPress\Exception\ValidationException;

try {
    $client->notifications->send(SendNotification::make($title, $message));
} catch (ValidationException $e) {
    echo $e->first('title');            // "The title may not be greater than 50 characters."
} catch (PaidPlanException $e) {
    echo $e->upgradeUrl;
} catch (RateLimitException $e) {
    sleep($e->retryAfter ?? 5);
}
```

Credentials never reach a message or a stack trace — see `NotifyPress\Http\Redactor`, which is what everything diagnostic goes through.

## Retries

Idempotent requests are retried up to `maxRetries` times (default 2) on 429/502/503/504 and on connection failures, honouring `Retry-After` up to a 10-second cap, otherwise exponential backoff with jitter.

**Mutations are never retried automatically.** A send that times out may already have gone out, and retrying it would push a duplicate to the entire audience. Opt in per call when a request really is safe to repeat:

```php
use NotifyPress\Http\RequestOptions;

$client->tags->delete($tagId, RequestOptions::make()->retryable());
```

## Scheduling and timezones

Every time the API stores is UTC, and every time it returns is ISO 8601 with an offset.

`scheduled_at` must name one exact instant. Pass a `DateTime` in your user's timezone: the SDK sends it with its offset (`DateTimeInterface::ATOM`), so 09:30 in Dhaka and 09:30 in Toronto are different, correct moments, daylight saving included:

```php
$client->notifications->schedule(
    ScheduleNotification::make('Morning', 'Rise and shine')
        ->at(new DateTimeImmutable('2026-08-15 09:30:00', new DateTimeZone('Asia/Dhaka')))
);
// sends "scheduled_at": "2026-08-15T09:30:00+06:00"
```

A string must carry its offset too (`'2026-08-15T09:30:00+06:00'` or `'2026-08-15T03:30:00Z'`). A bare `'2026-08-15 09:30:00'` is ambiguous, so the SDK throws `InvalidArgumentException` before sending (the API would reject it).

Recurring sends repeat at a local time. Say which timezone it is in; without `inTimezone()` the API uses the account's saved timezone:

```php
RecurringSchedule::weekly('09:30')->onDays([1, 3, 5])->inTimezone('Asia/Dhaka');   // Mon/Wed/Fri 09:30 Dhaka
RecurringSchedule::monthly('09:30')->onDayOfMonth(15)->inTimezone('America/Toronto');
```

Datetimes on responses stay ISO strings (`$notification->scheduledAt`), with `DateTimeImmutable` helpers when you need one:

```php
$local = $notification->scheduledAtDate()?->setTimezone(new DateTimeZone('Asia/Dhaka'));
```

## Uploads

`MultipartBody` builds the request bytes by hand, so one code path works across every transport:

```php
use NotifyPress\Http\Multipart\MultipartBody;

$client->notifications->create(
    MultipartBody::make()
        ->field('title', 'Flash sale')
        ->field('message', '25% off')
        ->file('icon', '/path/to/icon.png')      // max 2 MB
        ->file('image', '/path/to/hero.jpg')     // max 4 MB
);
```

Oversized or wrongly-typed files fail locally with the same field key the API would have used, so there is one error path either way. `->withoutClientValidation()` defers entirely to the server.

## Public endpoints

For visitor opt-in, keyed by the public `npk_` key:

```php
use NotifyPress\NotifyPressPublic;
use NotifyPress\Payload\PublicSubscribe;

$push = new NotifyPressPublic('npk_...', 'https://example.com');

$push->subscribe(PublicSubscribe::make($fcmToken)->browser('Chrome')->device('Desktop'));
$push->unsubscribe($fcmToken);          // keeps the row, marks it inactive
$push->erase($fcmToken);                // GDPR hard delete, 5/min
$push->recentNotifications();
```

**The site URL is required.** The server validates `Origin`/`Referer` against the site registered in NotifyPress (exact host or the `www.` variant) and rejects requests carrying neither. A browser sets that header itself; server-side PHP does not, so the SDK sends it explicitly. Give the URL registered in NotifyPress, or every call will 403.

## Forward compatibility

The SDK is built not to break when the API grows:

- Models keep the full decoded payload. A new field is readable through `$model->get('new.field')` instead of being dropped.
- Enums are read with `tryFrom()`. A status this release has never heard of leaves `statusEnum()` null while `$model->status` still holds the raw string — it never fatals.
- `$client->transport()` reaches any endpoint the SDK does not model yet, with auth, retries, and error mapping intact.

## Development

```bash
composer install
composer test      # Pest, no network
composer phpstan   # level 8, analysed at the 8.1 floor
composer cs        # PSR-12
composer check     # all three
```

## License

MIT
