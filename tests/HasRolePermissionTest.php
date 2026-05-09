<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests;

use Authn\Sdk\Laravel\Facades\Authn;
use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Illuminate\Support\Facades\Route;

final class HasRolePermissionTest extends TestCase
{
    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware(AuthenticateWithAuthn::class)->get('/has-role', function () {
            return response()->json(['result' => Authn::hasRole('org:admin')]);
        });

        Route::middleware(AuthenticateWithAuthn::class)->get('/has-permission', function () {
            return response()->json(['result' => Authn::hasPermission('org:foo:bar')]);
        });
    }

    public function test_has_role_returns_true_when_jwt_carries_matching_role(): void
    {
        $jwt = $this->env->signJwt(['org' => ['id' => 'org_1', 'rol' => 'org:admin']]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-role')
            ->assertOk()
            ->assertJson(['result' => true]);
    }

    public function test_has_role_returns_false_when_role_does_not_match(): void
    {
        $jwt = $this->env->signJwt(['org' => ['id' => 'org_1', 'rol' => 'org:member']]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-role')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_has_role_returns_false_when_no_org_claim(): void
    {
        $jwt = $this->env->signJwt();

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-role')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_has_permission_returns_true_when_jwt_carries_matching_permission(): void
    {
        $jwt = $this->env->signJwt(['org' => ['id' => 'org_1', 'per' => ['org:foo:bar']]]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-permission')
            ->assertOk()
            ->assertJson(['result' => true]);
    }

    public function test_has_permission_returns_false_when_permission_does_not_match(): void
    {
        $jwt = $this->env->signJwt(['org' => ['id' => 'org_1', 'per' => ['org:other']]]);

        $this->withHeader('Authorization', "Bearer {$jwt}")
            ->get('/has-permission')
            ->assertOk()
            ->assertJson(['result' => false]);
    }

    public function test_has_role_and_permission_return_false_when_no_claims_bound(): void
    {
        expect(Authn::hasRole('org:admin'))->toBeFalse()
            ->and(Authn::hasPermission('org:foo:bar'))->toBeFalse();
    }
}
