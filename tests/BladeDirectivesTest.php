<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests;

use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

final class BladeDirectivesTest extends TestCase
{
    private const AUTHENTICATED_TEMPLATE = <<<'BLADE'
@authnSignedIn
SIGNED_IN
@endauthnSignedIn
@authnSignedOut
SIGNED_OUT
@endauthnSignedOut
@authnHas('role:org:admin')
HAS_ROLE
@else
NO_ROLE
@endauthnHas
BLADE;

    private const ANONYMOUS_TEMPLATE = <<<'BLADE'
@authnSignedIn
SIGNED_IN
@endauthnSignedIn
@authnSignedOut
SIGNED_OUT
@endauthnSignedOut
BLADE;

    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render',
            fn () => Blade::render(self::AUTHENTICATED_TEMPLATE),
        );

        Route::get('/render-anonymous', fn () => Blade::render(self::ANONYMOUS_TEMPLATE));
    }

    public function test_renders_the_signed_in_branch_when_middleware_has_populated_claims(): void
    {
        $jwt = $this->env->signJwt();

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render')->getContent();

        $this->assertStringContainsString('SIGNED_IN', (string) $body);
        $this->assertStringNotContainsString('SIGNED_OUT', (string) $body);
    }

    public function test_authn_has_always_renders_the_else_branch_in_v01(): void
    {
        $jwt = $this->env->signJwt();

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/render')->getContent();

        $this->assertStringContainsString('NO_ROLE', (string) $body);
        $this->assertStringNotContainsString('HAS_ROLE', (string) $body);
    }

    public function test_renders_the_signed_out_branch_on_an_anonymous_request(): void
    {
        $body = $this->get('/render-anonymous')->getContent();

        $this->assertStringContainsString('SIGNED_OUT', (string) $body);
        $this->assertStringNotContainsString('SIGNED_IN', (string) $body);
    }
}
