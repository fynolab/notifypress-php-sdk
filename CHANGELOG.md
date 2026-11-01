# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
