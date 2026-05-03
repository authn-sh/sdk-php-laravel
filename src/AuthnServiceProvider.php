<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel;

use Illuminate\Support\ServiceProvider;

/**
 * Container bindings, config publishing, middleware aliasing, and Blade directive
 * registration land in SPL-2 / SPL-3. This skeleton just makes the package
 * auto-discoverable so a fresh `composer require authn-sh/sdk-php-laravel` boots
 * cleanly in a Laravel 11 / 12 application.
 */
class AuthnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SPL-2 wires Client / TokenVerifier / SignatureVerifier here.
    }

    public function boot(): void
    {
        // SPL-2 publishes config/authn.php; SPL-3 registers middleware + Blade directives.
    }
}
