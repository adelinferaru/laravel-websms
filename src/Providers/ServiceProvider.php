<?php

namespace Adelinferaru\LaravelWebSms\Providers;

use Adelinferaru\LaravelWebSms\LaravelWebSms;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Service provider
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $configFile = __DIR__ . '/../config/websms.php';

        $this->publishes([
            __DIR__ . '/../config/websms.php' => config_path('websms.php')
        ]);




    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/websms.php', 'websms');

        $this->app->singleton(LaravelWebSms::class, function ($app) {
            return new LaravelWebSms($app['config']['websms']);
        });
    }
}
