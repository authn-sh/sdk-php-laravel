<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests;

use Authn\Sdk\Laravel\Facades\Authn;
use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Authn\Sdk\Laravel\Tests\Support\TestUser;
use Authn\Sdk\Tokens\VerifiedClaims;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

final class UserResolverTest extends TestCase
{
    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware(AuthenticateWithAuthn::class)->get('/whoami', fn () => response()->json([
            'auth_user_id' => Auth::user()?->getAuthIdentifier(),
        ]));
    }

    protected function tearDown(): void
    {
        // Resolver lives on the singleton manager; clear it between tests.
        Authn::resolveUserUsing(null);

        parent::tearDown();
    }

    public function test_routes_claims_through_the_resolver_and_binds_auth_user(): void
    {
        Authn::resolveUserUsing(fn (VerifiedClaims $claims) => new TestUser($claims->sub));

        $jwt = $this->env->signJwt(['sub' => 'user_42']);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/whoami')
            ->assertOk()
            ->assertJson(['auth_user_id' => 'user_42']);
    }

    public function test_leaves_auth_user_null_when_no_resolver_is_registered(): void
    {
        $jwt = $this->env->signJwt();

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/whoami')
            ->assertOk()
            ->assertJson(['auth_user_id' => null]);
    }

    public function test_leaves_auth_user_null_when_the_resolver_returns_null(): void
    {
        Authn::resolveUserUsing(static fn (VerifiedClaims $claims): ?TestUser => null);

        $jwt = $this->env->signJwt();

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/whoami')
            ->assertOk()
            ->assertJson(['auth_user_id' => null]);
    }
}
