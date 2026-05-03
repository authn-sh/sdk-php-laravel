<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Http\Middleware;

use Authn\Sdk\Laravel\AuthnManager;
use Authn\Sdk\Tokens\TokenInvalidException;
use Authn\Sdk\Tokens\TokenVerifier;
use Authn\Sdk\Tokens\VerifiedClaims;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithAuthn
{
    public const COOKIE_NAME = '__session';

    public const REQUEST_ATTRIBUTE = 'authn.claims';

    public function __construct(
        private readonly TokenVerifier $verifier,
        private readonly AuthnManager $manager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $jwt = $this->extractJwt($request);

        if ($jwt === null) {
            return $this->unauthorized('No authn.sh session token on the request.');
        }

        try {
            $claims = $this->verifier->verify($jwt);
        } catch (TokenInvalidException $e) {
            return $this->unauthorized($e->getMessage());
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $claims);

        $this->bindResolvedUser($claims);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    private function extractJwt(Request $request): ?string
    {
        $authorization = $request->headers->get('authorization');
        if (is_string($authorization) && str_starts_with($authorization, 'Bearer ')) {
            $token = trim(substr($authorization, 7));
            if ($token !== '') {
                return $token;
            }
        }

        $cookie = $request->cookies->get(self::COOKIE_NAME);

        return is_string($cookie) && $cookie !== '' ? $cookie : null;
    }

    private function bindResolvedUser(VerifiedClaims $claims): void
    {
        $resolver = $this->manager->getUserResolver();
        if ($resolver === null) {
            return;
        }

        $user = $resolver($claims);
        if ($user instanceof Authenticatable) {
            Auth::guard()->setUser($user);
        }
    }

    private function unauthorized(string $message): JsonResponse
    {
        return new JsonResponse([
            'errors' => [[
                'code' => 'authn_unauthorized',
                'message' => $message,
            ]],
        ], 401);
    }
}
