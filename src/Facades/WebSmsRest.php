<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Facades;

use Adelinferaru\LaravelWebSms\WebSmsRestClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static object sendSms(string $from, string $to, string $message, string|\Adelinferaru\LaravelWebSms\DataCoding $encoding = \Adelinferaru\LaravelWebSms\DataCoding::Gsm)
 * @method static list<object> sendSmsToMany(string $from, list<string> $to, string $message, string|\Adelinferaru\LaravelWebSms\DataCoding $encoding = \Adelinferaru\LaravelWebSms\DataCoding::Gsm)
 * @method static bool checkKey()
 * @method static float getCredits()
 * @method static object getBatchStatus(int|string $batchId)
 *
 * @see WebSmsRestClient
 */
class WebSmsRest extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WebSmsRestClient::class;
    }
}
