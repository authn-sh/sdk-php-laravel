<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests;

use Authn\Sdk\Laravel\Facades\Authn;
use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

final class HasAuthorizedAppTest extends TestCase
{
    private const DIRECTIVE_TEMPLATE = <<<'BLADE'
@authnHasAuthorizedApp('oac_dashboard')
HAS_DASHBOARD
@else
NO_DASHBOARD
@endauthnHasAuthorizedApp
BLADE;

    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware(AuthenticateWithAuthn::class)->get('/has-app-dashboard', function () {
            return response()->json(['result' => Authn::hasAuthorizedApp('oac_dashboard')]);
        });

        Route::middleware(AuthenticateWithAuthn::class)->get(
            '/render-authorized-app',
            fn () => Blade::render(self::DIRECTIVE_TEMPLATE),
        );

        Route::get(
            '/render-authorized-app-anonymous',
            fn () => Blade::render(self::DIRECTIVE_TEMPLATE),
        );
    }

    public function test_facade_returns_true_when_authorized_apps_carries_the_id(): void
    {
        $jwt = $this->env->signJwt([
            'authorized_apps' => ['oac_dashboard', 'oac_mobile'],
        ]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-app-dashboard')
            ->assertOk()
            ->assertJson(['result' => true]);
    }

    public function test_facade_returns_false_when_authorized_apps_omits_the_id(): void
    {
        $jwt = $this->env->signJwt([
            'authorized_apps' => ['oac_other'],
        ]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-app-dashboard')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_facade_returns_false_when_claim_absent_v07_default(): void
    {
        $jwt = $this->env->signJwt();

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-app-dashboard')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_facade_returns_false_when_authorized_apps_is_not_an_array(): void
    {
        $jwt = $this->env->signJwt(['authorized_apps' => 'oac_dashboard']);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-app-dashboard')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_facade_ignores_non_string_entries_in_authorized_apps(): void
    {
        $jwt = $this->env->signJwt([
            'authorized_apps' => ['oac_dashboard', 42, ['nested' => 'oac_dashboard'], ''],
        ]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-app-dashboard')
            ->assertOk()
            ->assertJson(['result' => true]);
    }

    public function test_blade_directive_renders_truthy_branch_when_app_is_granted(): void
    {
        $jwt = $this->env->signJwt([
            'authorized_apps' => ['oac_dashboard'],
        ]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/render-authorized-app')
            ->getContent();

        $this->assertStringContainsString('HAS_DASHBOARD', (string) $body);
        $this->assertStringNotContainsString('NO_DASHBOARD', (string) $body);
    }

    public function test_blade_directive_renders_else_branch_when_app_is_not_granted(): void
    {
        $jwt = $this->env->signJwt([
            'authorized_apps' => ['oac_mobile'],
        ]);

        $body = $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/render-authorized-app')
            ->getContent();

        $this->assertStringContainsString('NO_DASHBOARD', (string) $body);
        $this->assertStringNotContainsString('HAS_DASHBOARD', (string) $body);
    }

    public function test_blade_directive_renders_else_branch_for_anonymous_requests(): void
    {
        $body = $this->get('/render-authorized-app-anonymous')->getContent();

        $this->assertStringContainsString('NO_DASHBOARD', (string) $body);
        $this->assertStringNotContainsString('HAS_DASHBOARD', (string) $body);
    }

    public function test_returns_false_when_no_authn_middleware_has_run(): void
    {
        Route::get('/has-app-outside-middleware', function () {
            return response()->json(['result' => Authn::hasAuthorizedApp('oac_dashboard')]);
        });

        $this->get('/has-app-outside-middleware')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_returns_false_when_app_id_is_empty(): void
    {
        Route::middleware(AuthenticateWithAuthn::class)->get('/has-app-empty', function () {
            return response()->json(['result' => Authn::hasAuthorizedApp('')]);
        });

        $jwt = $this->env->signJwt(['authorized_apps' => ['oac_dashboard']]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-app-empty')
            ->assertOk()
            ->assertJson(['result' => false]);
    }
}
