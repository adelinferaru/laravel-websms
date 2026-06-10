<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Facades;

use Adelinferaru\LaravelWebSms\WebSmsClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static object sendSms(string $from, string|list<string> $to, string $message, string $encoding = 'GSM')
 * @method static object getCredits()
 * @method static object getBatchStatus(int|string $batchId)
 * @method static string authenticate()
 *
 * @see WebSmsClient
 */
class WebSms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WebSmsClient::class;
    }
}
