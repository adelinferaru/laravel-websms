# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
