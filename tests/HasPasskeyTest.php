<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests;

use Authn\Sdk\Laravel\Facades\Authn;
use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Illuminate\Support\Facades\Route;

final class HasPasskeyTest extends TestCase
{
    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware(AuthenticateWithAuthn::class)->get('/has-passkey-default', function () {
            return response()->json(['result' => Authn::hasPasskey()]);
        });

        Route::middleware(AuthenticateWithAuthn::class)->get('/has-passkey-enrolled', function () {
            return response()->json(['result' => Authn::hasPasskey('enrolled')]);
        });

        Route::middleware(AuthenticateWithAuthn::class)->get('/has-passkey-verified', function () {
            return response()->json(['result' => Authn::hasPasskey('verified')]);
        });
    }

    public function test_returns_true_when_session_is_passkey_verified(): void
    {
        $jwt = $this->env->signJwt(['pkv' => true, 'pkc' => 1]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-passkey-default')
            ->assertOk()
            ->assertJson(['result' => true]);
    }

    public function test_returns_false_in_default_mode_when_session_is_password_only(): void
    {
        $jwt = $this->env->signJwt(['pkv' => false, 'pkc' => 2]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-passkey-default')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_enrolled_mode_returns_true_for_any_passkey_count_above_zero(): void
    {
        $jwt = $this->env->signJwt(['pkv' => false, 'pkc' => 2]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-passkey-enrolled')
            ->assertOk()
            ->assertJson(['result' => true]);
    }

    public function test_returns_false_when_no_authn_middleware_has_run(): void
    {
        Route::get('/has-passkey-outside-middleware', function () {
            return response()->json(['result' => Authn::hasPasskey()]);
        });

        $this->get('/has-passkey-outside-middleware')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_honours_configured_default_strict_mode(): void
    {
        config(['authn.passkey.default_strict_mode' => 'enrolled']);

        $jwt = $this->env->signJwt(['pkv' => false, 'pkc' => 1]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-passkey-default')
            ->assertOk()
            ->assertJson(['result' => true]);
    }
}
