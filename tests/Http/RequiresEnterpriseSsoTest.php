<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests\Http;

use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Http\Middleware\RequiresEnterpriseSso;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Authn\Sdk\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class RequiresEnterpriseSsoTest extends TestCase
{
    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware([AuthenticateWithAuthn::class, 'authn.requires_enterprise_sso'])
            ->get('/sso-default', fn () => response()->json(['ok' => true]));

        Route::middleware([AuthenticateWithAuthn::class, 'authn.requires_enterprise_sso:verified'])
            ->get('/sso-verified', fn () => response()->json(['ok' => true]));

        Route::middleware([AuthenticateWithAuthn::class, 'authn.requires_enterprise_sso:linked'])
            ->get('/sso-linked', fn () => response()->json(['ok' => true]));

        Route::middleware([RequiresEnterpriseSso::class . ':verified'])
            ->get('/sso-no-authn', fn () => response()->json(['ok' => true]));
    }

    public function test_default_mode_passes_when_session_carries_entcon(): void
    {
        $jwt = $this->env->signJwt([
            'entcon' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA',
            'entacc' => 'entacc_01HKX9SY9V7H7TF8C8K7J9X4ZB',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/sso-default');

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function test_default_mode_redirects_when_session_is_password_only(): void
    {
        $jwt = $this->env->signJwt();

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/sso-default');

        $response->assertRedirect('/sign-in/enterprise-sso');
    }

    public function test_verified_mode_requires_entcon_on_the_current_session(): void
    {
        $jwt = $this->env->signJwt([
            'entcon' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/sso-verified');

        $response->assertOk();
    }

    public function test_verified_mode_rejects_even_if_user_has_linked_accounts_but_session_is_not_enterprise(): void
    {
        $jwt = $this->env->signJwt([
            'enterprise_accounts' => [
                ['enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA'],
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/sso-verified');

        $response->assertRedirect('/sign-in/enterprise-sso');
    }

    public function test_linked_mode_passes_when_user_has_at_least_one_linked_account_even_without_entcon(): void
    {
        $jwt = $this->env->signJwt([
            'enterprise_accounts' => [
                ['enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA'],
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/sso-linked');

        $response->assertOk();
    }

    public function test_linked_mode_passes_when_session_carries_entcon_only(): void
    {
        $jwt = $this->env->signJwt([
            'entcon' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/sso-linked');

        $response->assertOk();
    }

    public function test_linked_mode_redirects_when_user_has_no_enterprise_accounts(): void
    {
        $jwt = $this->env->signJwt();

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/sso-linked');

        $response->assertRedirect('/sign-in/enterprise-sso');
    }

    public function test_redirects_to_sign_in_when_unauthenticated(): void
    {
        $response = $this->get('/sso-no-authn');

        $response->assertRedirect('/sign-in');
    }

    public function test_uses_configured_redirect_url_when_blocked(): void
    {
        config(['authn.enterprise_sso.redirect_url' => '/auth/enterprise-start']);

        $jwt = $this->env->signJwt();

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/sso-default');

        $response->assertRedirect('/auth/enterprise-start');
    }

    public function test_uses_configured_sign_in_url_when_unauthenticated(): void
    {
        config(['authn.url.sign_in' => '/auth/sign-in']);

        $response = $this->get('/sso-no-authn');

        $response->assertRedirect('/auth/sign-in');
    }

    public function test_default_strict_mode_can_be_configured_to_linked(): void
    {
        config(['authn.enterprise_sso.default_strict_mode' => 'linked']);

        $jwt = $this->env->signJwt([
            'enterprise_accounts' => [
                ['enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA'],
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/sso-default');

        $response->assertOk();
    }

    public function test_throws_when_default_strict_mode_is_garbage(): void
    {
        config(['authn.enterprise_sso.default_strict_mode' => 'unicorn']);

        $jwt = $this->env->signJwt(['entcon' => 'entcon_x']);

        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);

        $this->withHeader('Authorization', "Bearer {$jwt}")->get('/sso-default');
    }
}
