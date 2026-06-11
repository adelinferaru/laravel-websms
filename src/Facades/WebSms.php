<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Facades;

use Adelinferaru\LaravelWebSms\WebSmsClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static object sendSms(string $from, string|list<string> $to, string $message, string|\Adelinferaru\LaravelWebSms\DataCoding $encoding = \Adelinferaru\LaravelWebSms\DataCoding::Gsm, ?\DateTimeInterface $scheduledFor = null)
 * @method static object cancelScheduledBatch(string $batchId)
 * @method static float getCredits()
 * @method static object getBatchStatus(int|string $batchId)
 * @method static object getIncomingMessages(?\DateTimeInterface $since = null, ?int $afterId = null)
 * @method static object pushSms(string $contactPhone, string $message, ?string $to = null, ?\DateTimeInterface $sendAt = null)
 * @method static object createContactGroup(string $groupName)
 * @method static object listContactGroups(?int $start = null, ?int $number = null)
 * @method static object addContact(string $contactName, string $contactPhone, ?int $groupId = null)
 * @method static object checkContactInGroup(string $contactPhone, ?int $groupId = null)
 * @method static object removeContactFromGroup(?string $contactPhone = null, ?int $contactId = null, ?int $groupId = null)
 * @method static bool isSessionValid()
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
