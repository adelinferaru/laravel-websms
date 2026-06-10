<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Tests;

use Adelinferaru\LaravelWebSms\Facades\WebSms;
use Adelinferaru\LaravelWebSms\WebSmsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [WebSmsServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['WebSms' => WebSms::class];
    }
}
