<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests;

use Authn\Sdk\Laravel\AuthnServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AuthnServiceProvider::class];
    }
}
