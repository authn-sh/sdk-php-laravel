<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests\Http;

use Authn\Sdk\Laravel\Facades\Authn;
use Authn\Sdk\Laravel\Http\Middleware\AuthenticateWithAuthn;
use Authn\Sdk\Laravel\Tests\Support\AuthnTestEnvironment;
use Authn\Sdk\Laravel\Tests\Support\JwtFixture;
use Authn\Sdk\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class MiddlewareTest extends TestCase
{
    private AuthnTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new AuthnTestEnvironment;
        $this->env->bind($this->app);

        Route::middleware(AuthenticateWithAuthn::class)->get('/me', function () {
            return response()->json([
                'sub' => Authn::auth()?->sub,
                'sid' => Authn::auth()?->sid,
                'attribute_present' => request()->attributes->has(AuthenticateWithAuthn::REQUEST_ATTRIBUTE),
            ]);
        });
    }

    public function test_returns_200_with_claims_when_a_valid_jwt_is_in_the_authorization_header(): void
    {
        $jwt = $this->env->signJwt();

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/me');

        $response->assertOk();
        $response->assertJson([
            'sub' => 'user_2x',
            'sid' => 'sess_1y',
            'attribute_present' => true,
        ]);
    }

    public function test_returns_401_with_bapi_envelope_when_no_jwt_is_present(): void
    {
        $response = $this->get('/me');

        $response->assertStatus(401);
        $response->assertJson(['errors' => [['code' => 'authn_unauthorized']]]);
    }

    public function test_returns_401_when_the_jwt_signature_is_invalid(): void
    {
        $forged = (new JwtFixture)->sign($this->env->claims());

        $response = $this->withHeader('Authorization', "Bearer {$forged}")->get('/me');

        $response->assertStatus(401);
        $response->assertJson(['errors' => [['code' => 'authn_unauthorized']]]);
    }

    public function test_returns_401_when_the_jwt_has_expired(): void
    {
        $now = time();
        $jwt = $this->env->signJwt(['iat' => $now - 7200, 'exp' => $now - 60]);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/me');

        $response->assertStatus(401);
    }

    public function test_reads_the_jwt_from_the_session_cookie_when_no_header_is_set(): void
    {
        $jwt = $this->env->signJwt();

        $response = $this->withUnencryptedCookie(AuthenticateWithAuthn::COOKIE_NAME, $jwt)->get('/me');

        $response->assertOk();
        $response->assertJson(['sub' => 'user_2x']);
    }

    public function test_exposes_verified_claims_through_the_authn_facade_during_the_request(): void
    {
        $jwt = $this->env->signJwt(['sub' => 'user_xyz']);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")->get('/me');

        $response->assertJson(['sub' => 'user_xyz']);
    }

    public function test_returns_null_from_authn_auth_outside_of_a_request(): void
    {
        $this->assertNull(Authn::auth());
    }
}
