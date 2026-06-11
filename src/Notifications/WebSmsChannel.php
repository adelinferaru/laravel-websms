<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Notifications;

use Adelinferaru\LaravelWebSms\Exceptions\WebSmsException;
use Adelinferaru\LaravelWebSms\WebSmsClient;
use Illuminate\Notifications\Notification;

class WebSmsChannel
{
    public function __construct(
        private readonly WebSmsClient $client,
        private readonly ?string $defaultFrom = null,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $recipients = $this->recipients($notifiable, $notification);

        if ($recipients === []) {
            return;
        }

        $message = $this->message($notifiable, $notification);

        $from = $message->from ?? $this->defaultFrom;

        if ($from === null || $from === '') {
            throw new WebSmsException(
                'No SMS sender ID configured. Set WEBSMS_FROM or call WebSmsMessage::from().'
            );
        }

        $this->client->sendSms(
            $from,
            $recipients,
            $message->content,
            $message->encoding,
            $message->scheduledFor,
        );
    }

    /**
     * @return list<string>
     */
    private function recipients(object $notifiable, Notification $notification): array
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return [];
        }

        $route = $notifiable->routeNotificationFor('websms', $notification);

        if (is_string($route) && $route !== '') {
            return [$route];
        }

        if (is_array($route)) {
            return array_values(array_filter($route, static fn ($to): bool => is_string($to) && $to !== ''));
        }

        return [];
    }

    private function message(object $notifiable, Notification $notification): WebSmsMessage
    {
        if (! method_exists($notification, 'toWebsms')) {
            throw new WebSmsException(sprintf(
                'Notification %s must define a toWebsms() method to use the websms channel.',
                $notification::class,
            ));
        }

        $message = $notification->toWebsms($notifiable);

        if (is_string($message)) {
            return new WebSmsMessage($message);
        }

        if (! $message instanceof WebSmsMessage) {
            throw new WebSmsException('toWebsms() must return a WebSmsMessage instance or a string.');
        }

        return $message;
    }
}
