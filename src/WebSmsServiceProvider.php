<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WebSmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/websms.php', 'websms');

        $this->app->singleton(WebSmsClient::class, function (Application $app): WebSmsClient {
            /** @var array{wsdl: string, username: string|null, password: string|null, session: array{store?: string|null, key: string, ttl: int}} $config */
            $config = $app->make('config')->get('websms');

            /** @var CacheFactory $cacheFactory */
            $cacheFactory = $app->make(CacheFactory::class);

            return new WebSmsClient(
                $cacheFactory->store($config['session']['store'] ?? null),
                $config,
            );
        });

        $this->app->alias(WebSmsClient::class, 'websms');
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
