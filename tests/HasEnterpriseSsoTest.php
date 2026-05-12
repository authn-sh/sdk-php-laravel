<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests;

use Authn\Sdk\Laravel\Facades\Authn;
use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Illuminate\Support\Facades\Route;

final class HasEnterpriseSsoTest extends TestCase
{
    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware(AuthenticateWithAuthn::class)->get('/has-enterprise-default', function () {
            return response()->json(['result' => Authn::hasEnterpriseSso()]);
        });

        Route::middleware(AuthenticateWithAuthn::class)->get('/has-enterprise-linked', function () {
            return response()->json(['result' => Authn::hasEnterpriseSso('linked')]);
        });

        Route::middleware(AuthenticateWithAuthn::class)->get('/has-enterprise-verified', function () {
            return response()->json(['result' => Authn::hasEnterpriseSso('verified')]);
        });
    }

    public function test_returns_true_when_session_was_enterprise_sso_verified(): void
    {
        $jwt = $this->env->signJwt(['entcon' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA']);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-enterprise-default')
            ->assertOk()
            ->assertJson(['result' => true]);
    }

    public function test_returns_false_in_default_mode_when_session_is_password_only_even_with_linked_accounts(): void
    {
        $jwt = $this->env->signJwt([
            'enterprise_accounts' => [
                ['enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA'],
            ],
        ]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-enterprise-default')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_linked_mode_returns_true_when_user_has_any_linked_enterprise_account(): void
    {
        $jwt = $this->env->signJwt([
            'enterprise_accounts' => [
                ['enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA'],
            ],
        ]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-enterprise-linked')
            ->assertOk()
            ->assertJson(['result' => true]);
    }

    public function test_returns_false_when_no_authn_middleware_has_run(): void
    {
        Route::get('/has-enterprise-outside-middleware', function () {
            return response()->json(['result' => Authn::hasEnterpriseSso()]);
        });

        $this->get('/has-enterprise-outside-middleware')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_honours_configured_default_strict_mode(): void
    {
        config(['authn.enterprise_sso.default_strict_mode' => 'linked']);

        $jwt = $this->env->signJwt([
            'enterprise_accounts' => [
                ['enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA'],
            ],
        ]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-enterprise-default')
            ->assertOk()
            ->assertJson(['result' => true]);
    }
}
