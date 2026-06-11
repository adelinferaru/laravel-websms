# laravel-websms

[![CI](https://github.com/adelinferaru/laravel-websms/actions/workflows/ci.yml/badge.svg)](https://github.com/adelinferaru/laravel-websms/actions/workflows/ci.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/adelinferaru/laravel-websms.svg)](https://packagist.org/packages/adelinferaru/laravel-websms)
[![Total Downloads](https://img.shields.io/packagist/dt/adelinferaru/laravel-websms.svg)](https://packagist.org/packages/adelinferaru/laravel-websms)
[![PHP Version](https://img.shields.io/packagist/php-v/adelinferaru/laravel-websms.svg)](composer.json)
[![License](https://img.shields.io/packagist/l/adelinferaru/laravel-websms.svg)](LICENSE)

Send SMS from Laravel applications through the [WebSMS.com.cy](https://www.websms.com.cy) gateway (Cyprus).

The package wraps the gateway's SOAP web service (full feature set: bulk and scheduled
sending, two-way SMS, contact groups) and its REST API (API-key sending), handles
authentication transparently, caches the gateway session via Laravel's cache, and ships
a native Laravel notification channel.

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

// Remaining account credits (float)
$credits = WebSms::getCredits();

// Delivery status of a sent batch (per-message DELIVERED / EXPIRED / UNDELIVERABLE / UNKNOWN)
$status = WebSms::getBatchStatus($batchId);
```

The gateway accepts at most 100 recipients per `sendSms()` call; the package validates
this before calling out.

### Scheduled messages

Pass a `DateTimeInterface` to defer delivery, and cancel a scheduled batch before it goes out:

```php
$response = WebSms::sendSms('ACME', $phone, 'Sale starts now!', scheduledFor: now()->addDay());

WebSms::cancelScheduledBatch($response->batchId);
```

### Two-way SMS (incoming messages)

The gateway exposes received messages via polling (there are no webhooks). Use the
cursors from each response to fetch only newer messages:

```php
$inbox = WebSms::getIncomingMessages();                       // first page
$more  = WebSms::getIncomingMessages($since, $lastMessageId); // newer than the cursors

foreach ($inbox->messages ?? [] as $message) {
    // $message->id, ->receivedOn, ->from, ->to, ->message, ->read
}

if ($inbox->hasMore) {
    // keep polling with the returned lastMessageDate / lastMessageId
}
```

### Contacts and groups

```php
WebSms::createContactGroup('Customers');
WebSms::listContactGroups();
WebSms::addContact('John', '+35799123456', $groupId);
WebSms::checkContactInGroup('+35799123456', $groupId);
WebSms::removeContactFromGroup('+35799123456', groupId: $groupId);

// Send (optionally scheduled) to a stored contact
WebSms::pushSms('+35799123456', 'Hello!', sendAt: now()->addHour());
```

## REST client

The package also ships a client for the gateway's REST API — a lighter transport that
authenticates with an API key (generated in your WebSMS account area) instead of a
username/password session. The REST API covers sending, credits, and batch status;
scheduling, two-way SMS, and contacts are SOAP-only.

```dotenv
WEBSMS_API_KEY=XXX-XXX-XXXXX
```

```php
use Adelinferaru\LaravelWebSms\Facades\WebSmsRest;

// The REST API takes one recipient per request
WebSmsRest::sendSms('ACME', '35799123456', 'Your order has shipped!');

// Convenience wrapper: one request per recipient
WebSmsRest::sendSmsToMany('ACME', ['35799123456', '35799654321'], 'Hello');

WebSmsRest::checkKey();              // bool
WebSmsRest::getCredits();            // float
WebSmsRest::getBatchStatus($batchId);
```

Recipient numbers use the `357...` or `00357...` prefix format; a leading `+` is
stripped automatically since the gateway rejects it. Sender IDs must be 3–11
characters. Message length per encoding: GSM 160/306/459 characters for 1/2/3
segments, UCS2 70/134/201.

`Adelinferaru\LaravelWebSms\WebSmsRestClient` is also container-resolvable for
constructor injection, like the SOAP client.

## Notification channel

The package registers a `websms` notification channel, so you can deliver Laravel
notifications as SMS. Set a default sender in `.env`:

```dotenv
WEBSMS_FROM=ACME
```

Return the channel from your notification and implement `toWebsms()`:

```php
use Adelinferaru\LaravelWebSms\Notifications\WebSmsMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification
{
    public function via(object $notifiable): array
    {
        return ['websms'];
    }

    public function toWebsms(object $notifiable): WebSmsMessage
    {
        return WebSmsMessage::create('Your order has shipped!')
            ->from('ACME')                       // optional, defaults to WEBSMS_FROM
            ->unicode()                          // optional, UCS2 for non-GSM text
            ->scheduledFor(now()->addMinutes(5)); // optional
    }
}
```

`toWebsms()` may also return a plain string. Tell the channel where to send by adding
a route to your notifiable:

```php
class User extends Authenticatable
{
    use Notifiable;

    public function routeNotificationForWebsms(): string
    {
        return $this->phone_number;
    }
}
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

`sendSms()` defaults to the `GSM` data coding — the only encodings the gateway accepts
are `GSM` and `UCS2` (Unicode). Pass the `DataCoding` enum (or its string value) when
sending non-GSM content:

```php
use Adelinferaru\LaravelWebSms\DataCoding;

WebSms::sendSms('ACME', $phone, 'Γειά σου', DataCoding::Ucs2);
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
