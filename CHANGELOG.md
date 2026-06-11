# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.1] - 2026-06-11

No code changes — documentation and packaging only.

### Fixed

- README examples now use the phone-number format each transport actually accepts,
  per the vendor's documentation: local Cypriot `9XXXXXXX` numbers for SOAP,
  `357...`/`00357...`-prefixed numbers for REST (the `+357` prefix is not supported).

### Added

- `SECURITY.md` (private vulnerability reporting + supported versions) and
  `CONTRIBUTING.md`; both excluded from dist archives.

## [2.2.0] - 2026-06-11

### Added

- `WebSmsRestClient` and the `WebSmsRest` facade: a REST transport authenticated with
  an API key (`WEBSMS_API_KEY`, optional `WEBSMS_REST_URL`) covering `sendSms()` (one
  recipient per request, with a `sendSmsToMany()` convenience), `checkKey()`,
  `getCredits()`, and `getBatchStatus()`. Recipient numbers get the unsupported
  leading `+` stripped automatically; sender IDs are validated (3–11 characters).
- New dependencies: `illuminate/http` and `guzzlehttp/guzzle`.

## [2.1.0] - 2026-06-11

### Added

- Scheduled sending: `sendSms(..., scheduledFor: $dateTime)` and `cancelScheduledBatch()`.
- Two-way SMS: `getIncomingMessages()` polls the inbox with optional date/ID cursors.
- `pushSms()` (send to a stored contact), `isSessionValid()`, and contact-group
  management: `createContactGroup()`, `listContactGroups()`, `addContact()`,
  `checkContactInGroup()`, `removeContactFromGroup()` — the package now wraps all 13
  operations the gateway WSDL defines.
- `websms` Laravel notification channel with a fluent `WebSmsMessage`
  (`content`/`from`/`unicode`/`encoding`/`scheduledFor`) and a `WEBSMS_FROM`
  default-sender config/env var.
- `DataCoding` enum (`GSM`/`UCS2`); `sendSms()` validates the encoding and the
  gateway's 100-recipients-per-call limit before calling out.

### Changed

- `getCredits()` returns the credit balance as `float` (previously the raw response
  object) — the WSDL defines the response as a bare float element.
- New dependency on `illuminate/notifications` for the notification channel.

### Fixed

- `getCredits()` now sends the bare `session_id` element the WSDL requires; 2.0.0
  wrapped it in a parameter object the gateway does not accept.

## [2.0.1] - 2026-06-11

### Changed

- Leaner dist archive: development files (tests, CI workflow, tooling configs and the
  changelog) are excluded from Composer installs via `.gitattributes` `export-ignore`.

## [2.0.0] - 2026-06-11

### Added

- Test suite (PHPUnit + Orchestra Testbench) covering the client and the service provider.
- Static analysis with PHPStan (Larastan, level max) and code style enforcement with Laravel Pint.
- GitHub Actions CI matrix: PHP 8.2–8.5 × Laravel 12/13, including a prefer-lowest job.
- `WebSmsException` for gateway/SOAP failures and `AuthenticationException` for invalid credentials.
- `WEBSMS_WSDL` and `WEBSMS_SESSION_STORE` environment variables.

### Changed

- **Breaking:** requires PHP 8.2+ and Laravel 12 or 13.
- **Breaking:** `LaravelWebSms` renamed to `WebSmsClient`; `sendSMS()`, `getCreditsLeft()` and
  `getBatch()` renamed to `sendSms()`, `getCredits()` and `getBatchStatus()`.
- **Breaking:** the gateway session is cached through Laravel's cache instead of a temp file;
  config keys `wsdl_file`, `session_path` and `session_ttl` replaced by `wsdl` and the
  `session` array (`store`, `key`, `ttl`).
- SOAP calls now use PHP's native `soap` extension instead of the nusoap library.
- Errors throw exceptions instead of calling `die()` or echoing raw SOAP request/response dumps.
- Config file moved from `src/config/websms.php` to `config/websms.php` and is published
  under the `websms-config` tag.

### Removed

- **Breaking:** the `econea/nusoap` dependency.
- Travis CI configuration and unused Laravel app test scaffolding.

### Fixed

- `getCredits()` (formerly `getCreditsLeft()`) sent a bare session string instead of the
  expected parameter object.
- The default session file path contained a stray space (`/tmp /websms.sess`).
