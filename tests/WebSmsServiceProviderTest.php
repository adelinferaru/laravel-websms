<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Tests;

use Adelinferaru\LaravelWebSms\WebSmsClient;

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
}
