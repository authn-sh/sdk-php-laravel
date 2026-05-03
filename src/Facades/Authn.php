<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Static-proxy facade for the authn.sh Laravel package.
 *
 * The accessor `authn.manager` is bound by AuthnServiceProvider in SPL-2; this
 * skeleton exists so the autodiscover alias `Authn` resolves to a real class
 * the moment the package is required, rather than failing at class-load time
 * because the class doesn't exist yet.
 */
class Authn extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'authn.manager';
    }
}
