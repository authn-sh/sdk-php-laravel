<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests\Http;

use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Http\Middleware\RequiresPasskey;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Authn\Sdk\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class RequiresPasskeyTest extends TestCase
{
    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware([AuthenticateWithAuthn::class, 'authn.requires_passkey'])
            ->get('/passkey-default', fn () => response()->json(['ok' => true]));

        Route::middleware([AuthenticateWithAuthn::class, 'authn.requires_passkey:verified'])
            ->get('/passkey-verified', fn () => response()->json(['ok' => true]));

        Route::middleware([AuthenticateWithAuthn::class, 'authn.requires_passkey:enrolled'])
            ->get('/passkey-enrolled', fn () => response()->json(['ok' => true]));

        Route::middleware([RequiresPasskey::class . ':verified'])
            ->get('/passkey-no-authn', fn () => response()->json(['ok' => true]));
    }

    public function test_default_mode_passes_when_session_is_passkey_verified(): void
    {
        $jwt = $this->env->signJwt(['pkv' => true, 'pkc' => 1]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/passkey-default');

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function test_default_mode_redirects_to_enroll_when_session_is_password_only(): void
    {
        $jwt = $this->env->signJwt();

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/passkey-default');

        $response->assertRedirect('/user/security/passkeys');
    }

    public function test_default_mode_redirects_to_enroll_when_user_has_passkeys_but_session_is_not_passkey_verified(): void
    {
        $jwt = $this->env->signJwt(['pkv' => false, 'pkc' => 2]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/passkey-default');

        $response->assertRedirect('/user/security/passkeys');
    }

    public function test_verified_mode_requires_passkey_session(): void
    {
        $jwt = $this->env->signJwt(['pkv' => true, 'pkc' => 1]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/passkey-verified');

        $response->assertOk();
    }

    public function test_enrolled_mode_passes_when_user_has_at_least_one_passkey_even_without_passkey_session(): void
    {
        $jwt = $this->env->signJwt(['pkv' => false, 'pkc' => 2]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/passkey-enrolled');

        $response->assertOk();
    }

    public function test_enrolled_mode_redirects_when_passkey_count_is_zero(): void
    {
        $jwt = $this->env->signJwt(['pkv' => false, 'pkc' => 0]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/passkey-enrolled');

        $response->assertRedirect('/user/security/passkeys');
    }

    public function test_redirects_to_sign_in_when_unauthenticated(): void
    {
        $response = $this->get('/passkey-no-authn');

        $response->assertRedirect('/sign-in');
    }

    public function test_uses_configured_enroll_url_when_blocked(): void
    {
        config(['authn.passkey.enroll_url' => '/security/keys']);

        $jwt = $this->env->signJwt();

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/passkey-default');

        $response->assertRedirect('/security/keys');
    }

    public function test_uses_configured_sign_in_url_when_unauthenticated(): void
    {
        config(['authn.url.sign_in' => '/auth/sign-in']);

        $response = $this->get('/passkey-no-authn');

        $response->assertRedirect('/auth/sign-in');
    }

    public function test_default_strict_mode_can_be_configured_to_enrolled(): void
    {
        config(['authn.passkey.default_strict_mode' => 'enrolled']);

        $jwt = $this->env->signJwt(['pkv' => false, 'pkc' => 1]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/passkey-default');

        $response->assertOk();
    }

    public function test_throws_when_default_strict_mode_is_garbage(): void
    {
        config(['authn.passkey.default_strict_mode' => 'unicorn']);

        $jwt = $this->env->signJwt(['pkv' => true]);

        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);

        $this->withHeader('Authorization', "Bearer {$jwt}")->get('/passkey-default');
    }
}
