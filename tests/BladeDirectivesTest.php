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

    private const CONNECTED_TEMPLATE = <<<'BLADE'
@authnHasConnectedAccount('google')
HAS_GOOGLE
@else
NO_GOOGLE
@endauthnHasConnectedAccount
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
            '/render-connected',
            fn () => Blade::render(self::CONNECTED_TEMPLATE),
        );

        Route::get('/render-connected-anonymous', fn () => Blade::render(self::CONNECTED_TEMPLATE));
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
}
