<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel;

use Authn\Sdk\Client;
use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Http\Middleware\RequiresConnectedAccount;
use Authn\Sdk\Laravel\Http\Middleware\RequiresMfa;
use Authn\Sdk\Laravel\Http\Middleware\RequiresPasskey;
use Authn\Sdk\Laravel\Support\ConnectedAccounts;
use Authn\Sdk\Laravel\Support\Passkeys;
use Authn\Sdk\Tokens\TokenVerifier;
use Authn\Sdk\Tokens\VerifiedClaims;
use Authn\Sdk\Webhooks\SignatureVerifier;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

class AuthnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'authn');

        $this->app->singleton(Client::class, $this->makeClient(...));
        $this->app->singleton(TokenVerifier::class, $this->makeTokenVerifier(...));
        $this->app->singleton(SignatureVerifier::class, $this->makeSignatureVerifier(...));

        $this->app->singleton('authn.manager', static fn (Container $app): AuthnManager => new AuthnManager($app));
        $this->app->alias('authn.manager', AuthnManager::class);

        $this->app->alias(Client::class, 'authn.client');
        $this->app->alias(TokenVerifier::class, 'authn.tokens');
        $this->app->alias(SignatureVerifier::class, 'authn.webhooks');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => $this->app->configPath('authn.php'),
            ], 'authn-config');
        }

        $this->registerMiddleware();
        $this->registerBladeDirectives();
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('authn', AuthenticateWithAuthn::class);
        $router->aliasMiddleware('authn.requires_mfa', RequiresMfa::class);
        $router->aliasMiddleware('authn.connected', RequiresConnectedAccount::class);
        $router->aliasMiddleware('authn.requires_passkey', RequiresPasskey::class);
    }

    private function registerBladeDirectives(): void
    {
        Blade::if('authnSignedIn', fn (): bool => self::currentClaims() !== null);
        Blade::if('authnSignedOut', fn (): bool => self::currentClaims() === null);

        Blade::if('authnRequiresMfa', fn (): bool => self::currentClaims()?->twoFactorVerified === true);

        Blade::if(
            'authnHasConnectedAccount',
            fn (string $providerKey): bool => ConnectedAccounts::has(self::currentClaims(), $providerKey),
        );

        Blade::if(
            'authnHasPasskey',
            fn (?string $mode = null): bool => Passkeys::matches(
                self::currentClaims(),
                $mode ?? self::configuredPasskeyMode(),
            ),
        );

        Blade::if('authnHas', function (string $check): bool {
            if (str_starts_with($check, 'role:')) {
                return self::currentClaims()?->hasRole(substr($check, 5)) ?? false;
            }

            if (str_starts_with($check, 'permission:')) {
                return self::currentClaims()?->hasPermission(substr($check, 11)) ?? false;
            }

            throw new InvalidArgumentException(
                "authnHas: unrecognised check \"{$check}\" — prefix with \"role:\" or \"permission:\".",
            );
        });
    }

    private static function configuredPasskeyMode(): string
    {
        $configured = config('authn.passkey.default_strict_mode');
        if (is_string($configured) && in_array($configured, [Passkeys::MODE_VERIFIED, Passkeys::MODE_ENROLLED], true)) {
            return $configured;
        }

        return Passkeys::MODE_VERIFIED;
    }

    private static function currentClaims(): ?VerifiedClaims
    {
        $app = \Illuminate\Container\Container::getInstance();
        if (! $app->bound('request')) {
            return null;
        }

        $request = $app->make('request');
        if (! $request instanceof Request) {
            return null;
        }

        $claims = $request->attributes->get(AuthenticateWithAuthn::REQUEST_ATTRIBUTE);

        return $claims instanceof VerifiedClaims ? $claims : null;
    }

    private function configPath(): string
    {
        return __DIR__ . '/../config/authn.php';
    }

    private function makeClient(Container $app): Client
    {
        $config = $this->config($app);
        $secretKey = $this->stringOrNull($config->get('authn.secret_key'));

        if ($secretKey === null) {
            throw new RuntimeException(
                'authn.sh secret key is missing — set AUTHN_SECRET_KEY in your environment.',
            );
        }

        return new Client(
            secretKey: $secretKey,
            apiUrl: $this->stringOrNull($config->get('authn.api_url')),
        );
    }

    private function makeTokenVerifier(Container $app): TokenVerifier
    {
        $config = $this->config($app);
        $publishableKey = $this->stringOrNull($config->get('authn.publishable_key'));

        if ($publishableKey === null) {
            throw new RuntimeException(
                'authn.sh publishable key is missing — set AUTHN_PUBLISHABLE_KEY in your environment.',
            );
        }

        return new TokenVerifier(
            publishableKey: $publishableKey,
            frontendApiUrl: $this->stringOrNull($config->get('authn.frontend_api_url')),
            cache: $this->resolveCache($app, $this->stringOrNull($config->get('authn.jwks_cache_store'))),
            jwksCacheTtlSeconds: $this->intConfig($config->get('authn.jwks_cache_ttl'), 600),
            allowedClockSkewSeconds: $this->intConfig($config->get('authn.allowed_clock_skew'), 5),
        );
    }

    private function makeSignatureVerifier(Container $app): SignatureVerifier
    {
        $config = $this->config($app);

        $secrets = [];
        $primary = $this->stringOrNull($config->get('authn.webhook_signing_secret'));
        if ($primary !== null) {
            $secrets[] = $primary;
        }

        $rotation = $config->get('authn.webhook_signing_secrets', []);
        if (is_array($rotation)) {
            foreach ($rotation as $secret) {
                if (is_string($secret) && $secret !== '') {
                    $secrets[] = $secret;
                }
            }
        }

        if ($secrets === []) {
            throw new RuntimeException(
                'authn.sh webhook signing secret is missing — set AUTHN_WEBHOOK_SECRET in your environment.',
            );
        }

        return new SignatureVerifier(
            signingSecret: $secrets,
            toleranceSeconds: $this->intConfig($config->get('authn.webhook_tolerance'), 300),
        );
    }

    private function resolveCache(Container $app, ?string $store): CacheInterface
    {
        /** @var CacheFactory $factory */
        $factory = $app->make('cache');

        $repository = $store !== null ? $factory->store($store) : $factory->store();

        if (! $repository instanceof CacheInterface) {
            throw new RuntimeException(
                'The configured cache store does not implement Psr\\SimpleCache\\CacheInterface.',
            );
        }

        return $repository;
    }

    private function config(Container $app): ConfigRepository
    {
        /** @var ConfigRepository $config */
        $config = $app->make('config');

        return $config;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function intConfig(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit(ltrim($value, '-'))) {
            return (int) $value;
        }

        return $default;
    }
}
