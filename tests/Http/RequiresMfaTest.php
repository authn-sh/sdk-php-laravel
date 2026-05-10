<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests\Http;

use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Http\Middleware\RequiresMfa;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Authn\Sdk\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class RequiresMfaTest extends TestCase
{
    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware([AuthenticateWithAuthn::class, RequiresMfa::class])->get('/mfa-protected', function () {
            return response()->json(['ok' => true]);
        });

        Route::middleware([AuthenticateWithAuthn::class, 'authn.requires_mfa:300'])->get('/mfa-protected-custom-age', function () {
            return response()->json(['ok' => true]);
        });

        Route::middleware(RequiresMfa::class)->get('/mfa-only', function () {
            return response()->json(['ok' => true]);
        });
    }

    public function test_passes_when_second_factor_age_is_within_max_age(): void
    {
        $jwt = $this->env->signJwt(['fva' => [60, 120]]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/mfa-protected');

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function test_redirects_to_factor_two_when_second_factor_was_never_proved(): void
    {
        $jwt = $this->env->signJwt(['fva' => [60, -1]]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/mfa-protected');

        $response->assertRedirect('/sign-in/factor-two');
    }

    public function test_redirects_to_factor_two_when_fva_claim_is_absent(): void
    {
        $jwt = $this->env->signJwt();

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/mfa-protected');

        $response->assertRedirect('/sign-in/factor-two');
    }

    public function test_redirects_to_factor_two_when_second_factor_age_exceeds_max_age(): void
    {
        $jwt = $this->env->signJwt(['fva' => [60, 9000]]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/mfa-protected');

        $response->assertRedirect('/sign-in/factor-two');
    }

    public function test_redirects_to_sign_in_when_not_authenticated(): void
    {
        $response = $this->get('/mfa-only');

        $response->assertRedirect('/sign-in');
    }

    public function test_respects_per_route_max_age_override_when_within_limit(): void
    {
        $jwt = $this->env->signJwt(['fva' => [60, 200]]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/mfa-protected-custom-age');

        $response->assertOk();
    }

    public function test_respects_per_route_max_age_override_when_over_limit(): void
    {
        $jwt = $this->env->signJwt(['fva' => [60, 400]]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/mfa-protected-custom-age');

        $response->assertRedirect('/sign-in/factor-two');
    }

    public function test_uses_configured_redirect_url_when_mfa_not_satisfied(): void
    {
        config(['authn.mfa.redirect_url' => '/auth/step-up']);

        $jwt = $this->env->signJwt(['fva' => [60, -1]]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/mfa-protected');

        $response->assertRedirect('/auth/step-up');
    }

    public function test_uses_configured_sign_in_url_when_not_authenticated(): void
    {
        config(['authn.url.sign_in' => '/auth/sign-in']);

        $response = $this->get('/mfa-only');

        $response->assertRedirect('/auth/sign-in');
    }
}
