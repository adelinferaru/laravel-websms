<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Tests;

use Adelinferaru\LaravelWebSms\Notifications\WebSmsChannel;
use Adelinferaru\LaravelWebSms\WebSmsClient;
use Adelinferaru\LaravelWebSms\WebSmsRestClient;
use Illuminate\Notifications\ChannelManager;

class WebSmsServiceProviderTest extends TestCase
{
    public function test_the_default_config_is_merged(): void
    {
        $this->assertSame(
            'https://www.websms.com.cy/webservices/websms.wsdl',
            config('websms.wsdl')
        );
        $this->assertSame('websms.session_id', config('websms.session.key'));
        $this->assertSame(1500, config('websms.session.ttl'));
    }

    public function test_the_client_is_registered_as_a_singleton(): void
    {
        $first = $this->app->make(WebSmsClient::class);
        $second = $this->app->make(WebSmsClient::class);

        $this->assertInstanceOf(WebSmsClient::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_the_websms_alias_resolves_the_client(): void
    {
        $this->assertSame(
            $this->app->make(WebSmsClient::class),
            $this->app->make('websms')
        );
    }

    public function test_the_rest_client_is_registered_as_a_singleton_with_its_alias(): void
    {
        $first = $this->app->make(WebSmsRestClient::class);

        $this->assertSame($first, $this->app->make(WebSmsRestClient::class));
        $this->assertSame($first, $this->app->make('websms.rest'));
    }

    public function test_the_rest_config_defaults_are_merged(): void
    {
        $this->assertSame('https://websms.com.cy/api', config('websms.rest.url'));
        $this->assertNull(config('websms.rest.key'));
    }

    public function test_the_websms_notification_channel_is_registered(): void
    {
        $channel = $this->app->make(ChannelManager::class)->channel('websms');

        $this->assertInstanceOf(WebSmsChannel::class, $channel);
    }

    public function test_the_default_sender_config_defaults_to_null(): void
    {
        $this->assertNull(config('websms.from'));
    }
}
