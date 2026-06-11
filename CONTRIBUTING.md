# Contributing

Thanks for considering a contribution!

## Local setup

```bash
git clone https://github.com/adelinferaru/laravel-websms.git
cd laravel-websms
composer install
```

PHP 8.2+ with the `soap` extension is required (`php -m | grep soap`).

## Before opening a PR

All three must pass — CI enforces them across PHP 8.2–8.5 × Laravel 12/13:

```bash
composer test      # PHPUnit
composer analyse   # PHPStan, level max — no baseline, no ignores
composer lint      # Pint (Laravel preset)
```

To check the lowest supported dependency set (CI runs this too):

```bash
composer update --prefer-lowest && composer test
```

## Guidelines

- Branch from `master`; one focused change per PR.
- Add tests for behavior changes — gateway calls are tested by mocking
  `SoapClient` (SOAP) or faking Laravel's HTTP client (REST), so no
  credentials or network access are needed.
- New gateway operations should match the WSDL
  (`https://www.websms.com.cy/webservices/websms.wsdl`) — parameter names and
  shapes come from there, not from guesswork.
- Update `CHANGELOG.md` under `[Unreleased]` (Keep a Changelog format).
- Commit messages describe the change and nothing else.
