# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **Breaking:** `scheduled_at` is now sent as an exact instant, ISO 8601 with the offset
  (`ScheduleNotification::DATE_FORMAT` is `DateTimeInterface::ATOM`). The NotifyPress API stores
  every time in UTC and rejects a bare `Y-m-d H:i:s`, which it used to read in the account's
  display timezone. `at()` and `scheduleDraft()` send a `DateTime` with its own offset, so build
  it in your user's timezone; strings must carry an offset.
- `ScheduleNotification::scheduledAt()` and `Notifications::scheduleDraft()` throw
  `InvalidArgumentException` for a string without an offset instead of sending it.
- Response timestamps are documented as ISO 8601 in UTC (they are no longer display-timezone
  wall-clock strings).

- **Breaking:** `Segments::list()` now returns a `CursorPage` and takes a query first
  (`list(Query|array $query = [], ?RequestOptions $options = null)`). It used to read only the
  first page, so accounts with more than 10 segments silently got 10. Page with `nextPage()` /
  `autoPaging()`, or ask for up to 100 per page with `SegmentQuery::perPage()`.

### Added

- `Notifications::capabilities()`: `['canSchedule' => bool, 'canRecur' => bool]` for the plan.
- `NotificationQuery::fullText()` and `excludeWelcome()`.
- `SubscriberQuery::sortByCreatedAt()`, the dashboard's order (index-backed on large sites).
- `CursorPage::$active`, `isPaidUser()` and `meta()`; `OffsetPage::statusCounts()` and `meta()`.
- `AutomationQuery::status()` and `search()`.
- `SegmentQuery::perPage()`, `Segment::$subscriberCount`, `$subscriberCountComputedAt` and
  `tags()`.
- Docblocks note that `Tags::create()` and `Subscribers::addTag()` are paid-plan features and
  throw `PaidPlanException` on the Free plan.
- `RecurringSchedule::inTimezone()`: the IANA timezone a recurring send's time and weekdays are
  in. The API keeps the local time across daylight saving changes.
- `Notification::$recurringTimezone`, and `scheduledAtDate()`, `nextRunAtDate()`,
  `sentAtDate()` and `createdAtDate()` returning `DateTimeImmutable`.

## [0.1.0] - 2026-08-03

Initial release — full parity with the JavaScript SDK.

### Added

- `NotifyPress` client covering every authenticated v1 endpoint across nine
  resources: sites, notifications, subscribers, tags, segments, automations,
  reports, announcements, dashboard.
- `NotifyPressPublic` for the unauthenticated visitor opt-in endpoints, sending
  the `Origin` header those endpoints require.
- Automatic HTTP client detection — WordPress, Guzzle via PSR-18, cURL, then PHP
  streams — so the package runs unchanged in WordPress, Laravel, and plain PHP
  with no required runtime dependencies.
- Typed exception hierarchy normalising all three server error body shapes,
  including `PaidPlanException::$upgradeUrl` and `RateLimitException::$retryAfter`.
- Automatic retries on 429/502/503/504 and connection failures for idempotent
  requests only, honouring `Retry-After` up to a 10-second cap.
- Fluent immutable payload and query builders; plain arrays accepted everywhere.
- Cursor and offset pagination, reading both of Laravel's cursor placements.
- `MultipartBody` for icon and image uploads, with client-side limits mirroring
  the server's.
- `Redactor`, keeping credentials out of logs, messages, and stack traces.

[Unreleased]: https://github.com/fynolab/notifypress-php-sdk/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/fynolab/notifypress-php-sdk/releases/tag/v0.1.0
