<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests\Http;

use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Http\Middleware\RequiresConnectedAccount;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Authn\Sdk\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class RequiresConnectedAccountTest extends TestCase
{
    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware([AuthenticateWithAuthn::class, 'authn.connected:google'])->get('/connected-google', function () {
            return response()->json(['ok' => true]);
        });

        Route::middleware([RequiresConnectedAccount::class . ':google'])->get('/connected-only', function () {
            return response()->json(['ok' => true]);
        });
    }

    public function test_passes_when_jwt_external_accounts_includes_provider_object_form(): void
    {
        $jwt = $this->env->signJwt([
            'external_accounts' => [
                ['provider' => 'google', 'external_id' => 'sub_1'],
                ['provider' => 'github'],
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/connected-google');

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function test_passes_when_jwt_eac_uses_compact_form_with_p_key(): void
    {
        $jwt = $this->env->signJwt([
            'eac' => [
                ['p' => 'google'],
                ['p' => 'github'],
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/connected-google');

        $response->assertOk();
    }

    public function test_passes_when_jwt_external_accounts_is_a_list_of_strings(): void
    {
        $jwt = $this->env->signJwt(['external_accounts' => ['google', 'github']]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/connected-google');

        $response->assertOk();
    }

    public function test_redirects_to_sso_callback_when_provider_not_linked(): void
    {
        $jwt = $this->env->signJwt(['external_accounts' => [['provider' => 'github']]]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/connected-google');

        $response->assertRedirect('/sign-in/sso-callback?provider=google');
    }

    public function test_redirects_when_external_accounts_claim_is_absent(): void
    {
        $jwt = $this->env->signJwt();

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/connected-google');

        $response->assertRedirect('/sign-in/sso-callback?provider=google');
    }

    public function test_redirects_to_sign_in_when_unauthenticated(): void
    {
        $response = $this->get('/connected-only');

        $response->assertRedirect('/sign-in');
    }

    public function test_uses_configured_redirect_url_with_provider_placeholder(): void
    {
        config(['authn.connected_accounts.redirect_url' => '/connect/{provider}/start']);

        $jwt = $this->env->signJwt();

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/connected-google');

        $response->assertRedirect('/connect/google/start');
    }

    public function test_uses_configured_sign_in_url_when_unauthenticated(): void
    {
        config(['authn.url.sign_in' => '/auth/sign-in']);

        $response = $this->get('/connected-only');

        $response->assertRedirect('/auth/sign-in');
    }

    public function test_throws_when_provider_key_parameter_is_missing(): void
    {
        Route::middleware([AuthenticateWithAuthn::class, RequiresConnectedAccount::class])->get('/connected-no-arg', function () {
            return response()->json(['ok' => true]);
        });

        $jwt = $this->env->signJwt();

        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);

        $this->withHeader('Authorization', "Bearer {$jwt}")->get('/connected-no-arg');
    }
}
