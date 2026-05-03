<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests\Support;

use Authn\Sdk\Tokens\TokenVerifier;
use Illuminate\Contracts\Foundation\Application;

/**
 * Spins up a TokenVerifier wired against a freshly-generated RSA keypair and
 * a stub PSR-18 client serving the matching JWKS. Tests that need to forge
 * JWTs against the bound verifier use this to swap the singleton.
 */
final class AuthnTestEnvironment
{
    public readonly JwtFixture $fixture;

    public readonly StaticBodyClient $http;

    public readonly MemoryCache $cache;

    public function __construct(public readonly string $frontendApiUrl = 'https://test.authn.sh')
    {
        $this->fixture = new JwtFixture;
        $this->http = new StaticBodyClient($this->fixture->jwksJson());
        $this->cache = new MemoryCache;
    }

    public function bind(?Application $app): void
    {
        if ($app === null) {
            throw new \RuntimeException('Cannot bind TokenVerifier without an application instance.');
        }

        $app->singleton(TokenVerifier::class, fn (): TokenVerifier => new TokenVerifier(
            publishableKey: 'pk_test_dummy',
            frontendApiUrl: $this->frontendApiUrl,
            cache: $this->cache,
            http: $this->http,
        ));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function claims(array $overrides = []): array
    {
        $now = time();

        return array_merge([
            'iss' => $this->frontendApiUrl,
            'sub' => 'user_2x',
            'sid' => 'sess_1y',
            'azp' => 'https://app.example',
            'iat' => $now - 1,
            'exp' => $now + 3600,
            'nbf' => $now - 5,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>|null  $claimsOverride
     */
    public function signJwt(?array $claimsOverride = null): string
    {
        return $this->fixture->sign($this->claims($claimsOverride ?? []));
    }
}
