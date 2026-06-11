<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms;

use Adelinferaru\LaravelWebSms\Notifications\WebSmsChannel;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class WebSmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/websms.php', 'websms');

        $this->app->singleton(WebSmsClient::class, function (Application $app): WebSmsClient {
            /** @var array{wsdl: string, username: string|null, password: string|null, from: string|null, session: array{store?: string|null, key: string, ttl: int}} $config */
            $config = $app->make('config')->get('websms');

            /** @var CacheFactory $cacheFactory */
            $cacheFactory = $app->make(CacheFactory::class);

            return new WebSmsClient(
                $cacheFactory->store($config['session']['store'] ?? null),
                $config,
            );
        });

        $this->app->alias(WebSmsClient::class, 'websms');

        $this->app->bind(WebSmsChannel::class, function (Application $app): WebSmsChannel {
            /** @var string|null $from */
            $from = $app->make('config')->get('websms.from');

            return new WebSmsChannel($app->make(WebSmsClient::class), $from);
        });

        Notification::resolved(static function (ChannelManager $service): void {
            $service->extend('websms', fn (Application $app): WebSmsChannel => $app->make(WebSmsChannel::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/websms.php' => config_path('websms.php'),
            ], 'websms-config');
        }
    }
}
