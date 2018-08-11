<?php namespace Adelinferaru\LaravelWebSms\Facades;

use Adelinferaru\LaravelWebSms\LaravelWebSms;
use \Illuminate\Support\Facades\Facade;

class WebSms extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor()
    {
        return LaravelWebSms::class;
    }
}
