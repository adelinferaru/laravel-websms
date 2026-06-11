<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Tests\Notifications;

use Adelinferaru\LaravelWebSms\DataCoding;
use Adelinferaru\LaravelWebSms\Exceptions\WebSmsException;
use Adelinferaru\LaravelWebSms\Notifications\WebSmsChannel;
use Adelinferaru\LaravelWebSms\Notifications\WebSmsMessage;
use Adelinferaru\LaravelWebSms\WebSmsClient;
use DateTimeImmutable;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WebSmsChannelTest extends TestCase
{
    private WebSmsClient&MockObject $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(WebSmsClient::class);
    }

    private function notifiable(string|array|null $route): object
    {
        return new class($route)
        {
            public function __construct(private readonly string|array|null $route) {}

            public function routeNotificationFor(string $channel, ?Notification $notification = null): string|array|null
            {
                return $this->route;
            }
        };
    }

    private function notification(WebSmsMessage|string $message): Notification
    {
        return new class($message) extends Notification
        {
            public function __construct(private readonly WebSmsMessage|string $message) {}

            public function toWebsms(object $notifiable): WebSmsMessage|string
            {
                return $this->message;
            }
        };
    }

    public function test_it_sends_a_message_with_all_options(): void
    {
        $scheduledFor = new DateTimeImmutable('2026-07-01T10:00:00+00:00');
        $message = WebSmsMessage::create('Your order shipped')
            ->from('ACME')
            ->unicode()
            ->scheduledFor($scheduledFor);

        $this->client->expects($this->once())
            ->method('sendSms')
            ->with('ACME', ['+35799123456'], 'Your order shipped', DataCoding::Ucs2, $scheduledFor);

        $channel = new WebSmsChannel($this->client, 'DEFAULT');
        $channel->send($this->notifiable('+35799123456'), $this->notification($message));
    }

    public function test_it_falls_back_to_the_default_sender(): void
    {
        $this->client->expects($this->once())
            ->method('sendSms')
            ->with('DEFAULT', ['+35799123456'], 'Hi', DataCoding::Gsm, null);

        $channel = new WebSmsChannel($this->client, 'DEFAULT');
        $channel->send($this->notifiable('+35799123456'), $this->notification(new WebSmsMessage('Hi')));
    }

    public function test_a_plain_string_from_to_websms_becomes_a_message(): void
    {
        $this->client->expects($this->once())
            ->method('sendSms')
            ->with('DEFAULT', ['+35799123456'], 'Plain text', DataCoding::Gsm, null);

        $channel = new WebSmsChannel($this->client, 'DEFAULT');
        $channel->send($this->notifiable('+35799123456'), $this->notification('Plain text'));
    }

    public function test_it_sends_to_multiple_routed_recipients(): void
    {
        $this->client->expects($this->once())
            ->method('sendSms')
            ->with('DEFAULT', ['+35799123456', '+35799654321'], 'Hi', DataCoding::Gsm, null);

        $channel = new WebSmsChannel($this->client, 'DEFAULT');
        $channel->send(
            $this->notifiable(['+35799123456', '+35799654321']),
            $this->notification(new WebSmsMessage('Hi'))
        );
    }

    public function test_it_skips_sending_when_the_notifiable_has_no_route(): void
    {
        $this->client->expects($this->never())->method('sendSms');

        $channel = new WebSmsChannel($this->client, 'DEFAULT');
        $channel->send($this->notifiable(null), $this->notification(new WebSmsMessage('Hi')));
    }

    public function test_it_throws_when_no_sender_is_available(): void
    {
        $this->client->expects($this->never())->method('sendSms');

        $channel = new WebSmsChannel($this->client, null);

        $this->expectException(WebSmsException::class);
        $this->expectExceptionMessage('No SMS sender ID configured');

        $channel->send($this->notifiable('+35799123456'), $this->notification(new WebSmsMessage('Hi')));
    }

    public function test_it_throws_when_the_notification_has_no_to_websms_method(): void
    {
        $this->client->expects($this->never())->method('sendSms');

        $notification = new class extends Notification {};

        $channel = new WebSmsChannel($this->client, 'DEFAULT');

        $this->expectException(WebSmsException::class);
        $this->expectExceptionMessage('must define a toWebsms() method');

        $channel->send($this->notifiable('+35799123456'), $notification);
    }
}
