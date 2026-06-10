# laravel-websms

[![CI](https://github.com/adelinferaru/laravel-websms/actions/workflows/ci.yml/badge.svg)](https://github.com/adelinferaru/laravel-websms/actions/workflows/ci.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/adelinferaru/laravel-websms.svg)](https://packagist.org/packages/adelinferaru/laravel-websms)
[![Total Downloads](https://img.shields.io/packagist/dt/adelinferaru/laravel-websms.svg)](https://packagist.org/packages/adelinferaru/laravel-websms)
[![License](https://img.shields.io/packagist/l/adelinferaru/laravel-websms.svg)](LICENSE)

Send SMS from Laravel applications through the [WebSMS.com.cy](https://www.websms.com.cy) gateway (Cyprus).

The package wraps the gateway's SOAP web service using PHP's native `soap` extension, handles
authentication transparently, and caches the gateway session via Laravel's cache so repeated
sends don't re-authenticate on every request.

## Requirements

- PHP 8.2+ with the `soap` extension
- Laravel 12 or 13

## Installation

```bash
composer require adelinferaru/laravel-websms
```

The service provider and the `WebSms` facade are registered via package auto-discovery.

Add your credentials to `.env`:

```dotenv
WEBSMS_USERNAME=your-username
WEBSMS_PASSWORD=your-password
```

Optionally publish the config file to tweak the WSDL endpoint or session caching:

```bash
php artisan vendor:publish --tag=websms-config
```

## Usage

Via the facade:

```php
use Adelinferaru\LaravelWebSms\Facades\WebSms;

// Single recipient
WebSms::sendSms('ACME', '+35799123456', 'Your order has shipped!');

// Multiple recipients
WebSms::sendSms('ACME', ['+35799123456', '+35799654321'], 'Flash sale today only.');

// Remaining account credits
$credits = WebSms::getCredits();

// Delivery status of a sent batch
$status = WebSms::getBatchStatus($batchId);
```

Or inject the client where you prefer constructor injection:

```php
use Adelinferaru\LaravelWebSms\WebSmsClient;

class OrderShippedNotifier
{
    public function __construct(private WebSmsClient $webSms) {}

    public function notify(string $phone): void
    {
        $this->webSms->sendSms('ACME', $phone, 'Your order has shipped!');
    }
}
```

### Error handling

All gateway failures throw `Adelinferaru\LaravelWebSms\Exceptions\WebSmsException`.
Invalid credentials throw the more specific
`Adelinferaru\LaravelWebSms\Exceptions\AuthenticationException` (a subclass), so you can
catch either level:

```php
use Adelinferaru\LaravelWebSms\Exceptions\WebSmsException;

try {
    WebSms::sendSms('ACME', $phone, $message);
} catch (WebSmsException $e) {
    report($e);
}
```

### Message encoding

`sendSms()` defaults to the `GSM` data coding. Pass a different encoding as the fourth
argument when sending non-GSM content:

```php
WebSms::sendSms('ACME', $phone, 'Γειά σου', 'UCS2');
```

## Upgrading from 1.x

Version 2 is a rewrite targeting Laravel 12+. The notable breaking changes:

| 1.x | 2.x |
| --- | --- |
| `LaravelWebSms` class | `WebSmsClient` |
| `sendSMS()` | `sendSms()` |
| `getCreditsLeft()` | `getCredits()` |
| `getBatch()` | `getBatchStatus()` |
| Session stored in a temp file | Session stored in the Laravel cache |
| `die()` / echoed SOAP dumps on failure | `WebSmsException` / `AuthenticationException` |
| nusoap client | Native PHP `soap` extension |

Config changes: the file is still published as `config/websms.php`, but `wsdl_file` is now
`wsdl`, and `session_path`/`session_ttl` were replaced by the `session` array
(`store`, `key`, `ttl`).

## Testing

```bash
composer test      # PHPUnit
composer analyse   # PHPStan (level max)
composer lint      # Pint
```

## License

The MIT License. Copyright (c) 2017-2026 Feraru Ioan Adelin. See [LICENSE](LICENSE).
