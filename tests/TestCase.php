<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests;

use Authn\Sdk\Laravel\AuthnServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Default publishable key — base64url-encoded "test.authn.sh$" so it
     * decodes through `Authn\Sdk\Util\PublishableKey::frontendApiUrl()`.
     */
    public const TEST_PUBLISHABLE_KEY = 'pk_test_dGVzdC5hdXRobi5zaCQ';

    public const TEST_SECRET_KEY = 'sk_test_default';

    public const TEST_WEBHOOK_SECRET = 'whsec_dGVzdC1zZWNyZXQ';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AuthnServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];

        $config->set('authn.secret_key', self::TEST_SECRET_KEY);
        $config->set('authn.publishable_key', self::TEST_PUBLISHABLE_KEY);
        $config->set('authn.webhook_signing_secret', self::TEST_WEBHOOK_SECRET);
    }
}
