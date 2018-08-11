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

        $configFile = __DIR__ . '/config/websms.php';

        $this->publishes([
            $configFile => config_path('websms.php')
        ]);

        $this->mergeConfigFrom($configFile, 'websms');


    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(LaravelWebSms::class, function ($app) {
            return new LaravelWebSms($app['config']['websms']);
        });
    }
}
