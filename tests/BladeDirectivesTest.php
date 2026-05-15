<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests;

use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\ViewException;

final class BladeDirectivesTest extends TestCase
{
    private const SIGNED_IN_OUT_TEMPLATE = <<<'BLADE'
@authnSignedIn
SIGNED_IN
@endauthnSignedIn
@authnSignedOut
SIGNED_OUT
@endauthnSignedOut
BLADE;

    private const ROLE_TEMPLATE = <<<'BLADE'
@authnHas('role:org:admin')
HAS_ROLE
@else
NO_ROLE
@endauthnHas
BLADE;

    private const PERMISSION_TEMPLATE = <<<'BLADE'
@authnHas('permission:org:foo:bar')
HAS_PERMISSION
@else
NO_PERMISSION
@endauthnHas
BLADE;

    private const MFA_TEMPLATE = <<<'BLADE'
@authnRequiresMfa
MFA_VERIFIED
@else
MFA_NOT_VERIFIED
@endauthnRequiresMfa
BLADE;

    private const MFA_TIGHT_WINDOW_TEMPLATE = <<<'BLADE'
@authnRequiresMfa(300)
MFA_VERIFIED
@else
MFA_NOT_VERIFIED
@endauthnRequiresMfa
BLADE;

    private const CONNECTED_TEMPLATE = <<<'BLADE'
@authnHasConnectedAccount('google')
HAS_GOOGLE
@else
NO_GOOGLE
@endauthnHasConnectedAccount
BLADE;

    private const PASSKEY_DEFAULT_TEMPLATE = <<<'BLADE'
@authnHasPasskey
HAS_PASSKEY
@else
NO_PASSKEY
@endauthnHasPasskey
BLADE;

    private const PASSKEY_ENROLLED_TEMPLATE = <<<'BLADE'
@authnHasPasskey('enrolled')
HAS_PASSKEY
@else
NO_PASSKEY
@endauthnHasPasskey
BLADE;

    private const ENTERPRISE_DEFAULT_TEMPLATE = <<<'BLADE'
@authnHasEnterpriseAccount
HAS_ENTERPRISE
@else
NO_ENTERPRISE
@endauthnHasEnterpriseAccount
BLADE;

    private const ENTERPRISE_CONNECTION_TEMPLATE = <<<'BLADE'
@authnHasEnterpriseAccount('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA')
HAS_ENTERPRISE
@else
NO_ENTERPRISE
@endauthnHasEnterpriseAccount
BLADE;

    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-signed-in-out',
            fn () => Blade::render(self::SIGNED_IN_OUT_TEMPLATE),
        );

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-role',
            fn () => Blade::render(self::ROLE_TEMPLATE),
        );

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-permission',
            fn () => Blade::render(self::PERMISSION_TEMPLATE),
        );

        Route::get('/render-anonymous', fn () => Blade::render(self::SIGNED_IN_OUT_TEMPLATE));

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-mfa',
            fn () => Blade::render(self::MFA_TEMPLATE),
        );

        Route::get('/render-mfa-anonymous', fn () => Blade::render(self::MFA_TEMPLATE));

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-mfa-tight',
            fn () => Blade::render(self::MFA_TIGHT_WINDOW_TEMPLATE),
        );

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-connected',
            fn () => Blade::render(self::CONNECTED_TEMPLATE),
        );

        Route::get('/render-connected-anonymous', fn () => Blade::render(self::CONNECTED_TEMPLATE));

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-passkey',
            fn () => Blade::render(self::PASSKEY_DEFAULT_TEMPLATE),
        );

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-passkey-enrolled',
            fn () => Blade::render(self::PASSKEY_ENROLLED_TEMPLATE),
        );

        Route::get('/render-passkey-anonymous', fn () => Blade::render(self::PASSKEY_DEFAULT_TEMPLATE));

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-enterprise',
            fn () => Blade::render(self::ENTERPRISE_DEFAULT_TEMPLATE),
        );

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-enterprise-connection',
            fn () => Blade::render(self::ENTERPRISE_CONNECTION_TEMPLATE),
        );

        Route::get('/render-enterprise-anonymous', fn () => Blade::render(self::ENTERPRISE_DEFAULT_TEMPLATE));
    }

    public function test_renders_the_signed_in_branch_when_middleware_has_populated_claims(): void
    {
        $jwt = $this->env->signJwt();

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-signed-in-out')->getContent();

        $this->assertStringContainsString('SIGNED_IN', (string) $body);
        $this->assertStringNotContainsString('SIGNED_OUT', (string) $body);
    }

    public function test_renders_the_signed_out_branch_on_an_anonymous_request(): void
    {
        $body = $this->get('/render-anonymous')->getContent();

        $this->assertStringContainsString('SIGNED_OUT', (string) $body);
        $this->assertStringNotContainsString('SIGNED_IN', (string) $body);
    }

    public function test_authn_has_role_renders_truthy_branch_when_jwt_carries_matching_role(): void
    {
        $jwt = $this->env->signJwt(['org' => ['id' => 'org_1', 'rol' => 'org:admin']]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-role')->getContent();

        $this->assertStringContainsString('HAS_ROLE', (string) $body);
        $this->assertStringNotContainsString('NO_ROLE', (string) $body);
    }

    public function test_authn_has_role_renders_else_branch_when_role_does_not_match(): void
    {
        $jwt = $this->env->signJwt(['org' => ['id' => 'org_1', 'rol' => 'org:member']]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-role')->getContent();

        $this->assertStringContainsString('NO_ROLE', (string) $body);
        $this->assertStringNotContainsString('HAS_ROLE', (string) $body);
    }

    public function test_authn_has_role_renders_else_branch_when_jwt_carries_no_org_claim(): void
    {
        $jwt = $this->env->signJwt();

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-role')->getContent();

        $this->assertStringContainsString('NO_ROLE', (string) $body);
        $this->assertStringNotContainsString('HAS_ROLE', (string) $body);
    }

    public function test_authn_has_permission_renders_truthy_branch_when_jwt_carries_matching_permission(): void
    {
        $jwt = $this->env->signJwt(['org' => ['id' => 'org_1', 'per' => ['org:foo:bar']]]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-permission')->getContent();

        $this->assertStringContainsString('HAS_PERMISSION', (string) $body);
        $this->assertStringNotContainsString('NO_PERMISSION', (string) $body);
    }

    public function test_authn_has_permission_renders_else_branch_when_permission_does_not_match(): void
    {
        $jwt = $this->env->signJwt(['org' => ['id' => 'org_1', 'per' => ['org:other']]]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-permission')->getContent();

        $this->assertStringContainsString('NO_PERMISSION', (string) $body);
        $this->assertStringNotContainsString('HAS_PERMISSION', (string) $body);
    }

    public function test_authn_has_throws_for_unrecognised_prefix(): void
    {
        $jwt = $this->env->signJwt();

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-garbage',
            fn () => Blade::render("@authnHas('garbage')\nFOO\n@endauthnHas"),
        );

        $this->withoutExceptionHandling();
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/unrecognised check/');

        $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-garbage');
    }

    public function test_authn_requires_mfa_renders_truthy_branch_when_second_factor_is_verified(): void
    {
        $jwt = $this->env->signJwt(['fva' => [60, 120]]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-mfa')->getContent();

        $this->assertStringContainsString('MFA_VERIFIED', (string) $body);
        $this->assertStringNotContainsString('MFA_NOT_VERIFIED', (string) $body);
    }

    public function test_authn_requires_mfa_renders_else_branch_when_second_factor_is_not_verified(): void
    {
        $jwt = $this->env->signJwt(['fva' => [60, -1]]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-mfa')->getContent();

        $this->assertStringContainsString('MFA_NOT_VERIFIED', (string) $body);
        $this->assertStringNotContainsString('MFA_VERIFIED', (string) $body);
    }

    public function test_authn_requires_mfa_renders_else_branch_when_fva_claim_is_absent(): void
    {
        $jwt = $this->env->signJwt();

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-mfa')->getContent();

        $this->assertStringContainsString('MFA_NOT_VERIFIED', (string) $body);
        $this->assertStringNotContainsString('MFA_VERIFIED', (string) $body);
    }

    public function test_authn_requires_mfa_renders_else_branch_on_anonymous_request(): void
    {
        $body = $this->get('/render-mfa-anonymous')->getContent();

        $this->assertStringContainsString('MFA_NOT_VERIFIED', (string) $body);
        $this->assertStringNotContainsString('MFA_VERIFIED', (string) $body);
    }

    public function test_authn_requires_mfa_renders_else_branch_when_second_factor_age_exceeds_default_max_age(): void
    {
        $jwt = $this->env->signJwt(['fva' => [60, 3600]]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-mfa')->getContent();

        $this->assertStringContainsString('MFA_NOT_VERIFIED', (string) $body);
        $this->assertStringNotContainsString('MFA_VERIFIED', (string) $body);
    }

    public function test_authn_requires_mfa_honours_configured_max_age_seconds(): void
    {
        config(['authn.mfa.max_age_seconds' => 60]);

        $jwt = $this->env->signJwt(['fva' => [10, 120]]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-mfa')->getContent();

        $this->assertStringContainsString('MFA_NOT_VERIFIED', (string) $body);
        $this->assertStringNotContainsString('MFA_VERIFIED', (string) $body);
    }

    public function test_authn_requires_mfa_argument_overrides_configured_max_age(): void
    {
        $jwt = $this->env->signJwt(['fva' => [10, 500]]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-mfa-tight')->getContent();

        $this->assertStringContainsString('MFA_NOT_VERIFIED', (string) $body);
        $this->assertStringNotContainsString('MFA_VERIFIED', (string) $body);
    }

    public function test_authn_has_connected_account_renders_truthy_branch_when_provider_is_linked(): void
    {
        $jwt = $this->env->signJwt([
            'external_accounts' => [
                ['provider' => 'google'],
            ],
        ]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-connected')->getContent();

        $this->assertStringContainsString('HAS_GOOGLE', (string) $body);
        $this->assertStringNotContainsString('NO_GOOGLE', (string) $body);
    }

    public function test_authn_has_connected_account_renders_else_branch_when_provider_is_not_linked(): void
    {
        $jwt = $this->env->signJwt([
            'external_accounts' => [
                ['provider' => 'github'],
            ],
        ]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-connected')->getContent();

        $this->assertStringContainsString('NO_GOOGLE', (string) $body);
        $this->assertStringNotContainsString('HAS_GOOGLE', (string) $body);
    }

    public function test_authn_has_connected_account_renders_else_branch_when_claim_is_absent(): void
    {
        $jwt = $this->env->signJwt();

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-connected')->getContent();

        $this->assertStringContainsString('NO_GOOGLE', (string) $body);
        $this->assertStringNotContainsString('HAS_GOOGLE', (string) $body);
    }

    public function test_authn_has_connected_account_renders_else_branch_on_anonymous_request(): void
    {
        $body = $this->get('/render-connected-anonymous')->getContent();

        $this->assertStringContainsString('NO_GOOGLE', (string) $body);
        $this->assertStringNotContainsString('HAS_GOOGLE', (string) $body);
    }

    public function test_authn_has_passkey_default_renders_truthy_branch_when_session_is_passkey_verified(): void
    {
        $jwt = $this->env->signJwt(['pkv' => true, 'pkc' => 1]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-passkey')->getContent();

        $this->assertStringContainsString('HAS_PASSKEY', (string) $body);
        $this->assertStringNotContainsString('NO_PASSKEY', (string) $body);
    }

    public function test_authn_has_passkey_default_renders_else_branch_when_user_has_passkey_but_session_is_password_only(): void
    {
        $jwt = $this->env->signJwt(['pkv' => false, 'pkc' => 3]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-passkey')->getContent();

        $this->assertStringContainsString('NO_PASSKEY', (string) $body);
        $this->assertStringNotContainsString('HAS_PASSKEY', (string) $body);
    }

    public function test_authn_has_passkey_enrolled_renders_truthy_branch_when_user_has_any_passkey(): void
    {
        $jwt = $this->env->signJwt(['pkv' => false, 'pkc' => 1]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-passkey-enrolled')->getContent();

        $this->assertStringContainsString('HAS_PASSKEY', (string) $body);
        $this->assertStringNotContainsString('NO_PASSKEY', (string) $body);
    }

    public function test_authn_has_passkey_enrolled_renders_else_branch_when_passkey_count_is_zero(): void
    {
        $jwt = $this->env->signJwt(['pkv' => false, 'pkc' => 0]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-passkey-enrolled')->getContent();

        $this->assertStringContainsString('NO_PASSKEY', (string) $body);
        $this->assertStringNotContainsString('HAS_PASSKEY', (string) $body);
    }

    public function test_authn_has_passkey_renders_else_branch_on_anonymous_request(): void
    {
        $body = $this->get('/render-passkey-anonymous')->getContent();

        $this->assertStringContainsString('NO_PASSKEY', (string) $body);
        $this->assertStringNotContainsString('HAS_PASSKEY', (string) $body);
    }

    public function test_authn_has_enterprise_account_default_renders_truthy_branch_when_session_carries_entcon(): void
    {
        $jwt = $this->env->signJwt(['entcon' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA']);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-enterprise')->getContent();

        $this->assertStringContainsString('HAS_ENTERPRISE', (string) $body);
        $this->assertStringNotContainsString('NO_ENTERPRISE', (string) $body);
    }

    public function test_authn_has_enterprise_account_default_renders_else_branch_when_session_is_password_only(): void
    {
        $jwt = $this->env->signJwt();

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-enterprise')->getContent();

        $this->assertStringContainsString('NO_ENTERPRISE', (string) $body);
        $this->assertStringNotContainsString('HAS_ENTERPRISE', (string) $body);
    }

    public function test_authn_has_enterprise_account_with_arg_matches_current_sessions_entcon(): void
    {
        $jwt = $this->env->signJwt(['entcon' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA']);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-enterprise-connection')->getContent();

        $this->assertStringContainsString('HAS_ENTERPRISE', (string) $body);
        $this->assertStringNotContainsString('NO_ENTERPRISE', (string) $body);
    }

    public function test_authn_has_enterprise_account_with_arg_matches_linked_account_for_that_connection(): void
    {
        $jwt = $this->env->signJwt([
            'enterprise_accounts' => [
                ['enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA'],
            ],
        ]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-enterprise-connection')->getContent();

        $this->assertStringContainsString('HAS_ENTERPRISE', (string) $body);
        $this->assertStringNotContainsString('NO_ENTERPRISE', (string) $body);
    }

    public function test_authn_has_enterprise_account_with_arg_renders_else_branch_for_other_connection(): void
    {
        $jwt = $this->env->signJwt([
            'entcon' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZZ',
            'enterprise_accounts' => [
                ['enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZZ'],
            ],
        ]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render-enterprise-connection')->getContent();

        $this->assertStringContainsString('NO_ENTERPRISE', (string) $body);
        $this->assertStringNotContainsString('HAS_ENTERPRISE', (string) $body);
    }

    public function test_authn_has_enterprise_account_renders_else_branch_on_anonymous_request(): void
    {
        $body = $this->get('/render-enterprise-anonymous')->getContent();

        $this->assertStringContainsString('NO_ENTERPRISE', (string) $body);
        $this->assertStringNotContainsString('HAS_ENTERPRISE', (string) $body);
    }
}
