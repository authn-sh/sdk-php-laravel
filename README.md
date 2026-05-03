# authn-sh/sdk-php-laravel

Laravel package for [authn.sh](https://authn.sh) — auto-discovered service provider, container bindings, middleware, Blade directives, and an `Authn` facade. Wraps [`authn-sh/sdk-php`](https://github.com/authn-sh/sdk-php) for Laravel 11+.

> Status: **0.1.x pre-release.** Bindings, middleware, and Blade directives land in SPL-2 / SPL-3. This release is a bootstrap-only skeleton.

## Requirements

- PHP **8.2+**
- Laravel **11+** (`^11.0 || ^12.0`)
- A PSR-18 HTTP client (Guzzle, Symfony HttpClient, etc.) — required by the underlying `authn-sh/sdk-php` SDK and auto-discovered via [`php-http/discovery`](https://docs.php-http.org/en/latest/discovery.html).

## Install

```bash
composer require authn-sh/sdk-php-laravel
```

The service provider auto-discovers. Once SPL-2 lands you'll be able to publish the config:

```bash
php artisan vendor:publish --tag=authn-config
```

…and resolve the SDK from the container or via the `Authn` facade.

## Development

```bash
composer install
composer test       # Pest + Orchestra Testbench
composer phpstan    # PHPStan + Larastan @ level 10
composer pint       # code style check
composer pint:fix   # apply fixes
```

CI runs the full suite on the **PHP 8.2 / 8.3 / 8.4 / 8.5 × Laravel 11 / 12** matrix.

## License

[AGPL-3.0-only](LICENSE).
