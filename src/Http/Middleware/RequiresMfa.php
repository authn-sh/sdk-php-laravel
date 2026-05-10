<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Http\Middleware;

use Authn\Sdk\Tokens\VerifiedClaims;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresMfa
{
    public function handle(Request $request, Closure $next, ?string $maxAgeOverride = null): Response
    {
        $claims = $request->attributes->get(AuthenticateWithAuthn::REQUEST_ATTRIBUTE);

        if (! $claims instanceof VerifiedClaims) {
            $signInUrl = config('authn.url.sign_in');

            return redirect(is_string($signInUrl) ? $signInUrl : '/sign-in');
        }

        if ($maxAgeOverride !== null) {
            $maxAge = (int) $maxAgeOverride;
        } else {
            $configured = config('authn.mfa.max_age_seconds');
            $maxAge = is_int($configured) ? $configured : 1800;
        }

        $age = $claims->secondFactorAgeSeconds;

        if ($age === null || $age > $maxAge) {
            $redirectUrl = config('authn.mfa.redirect_url');

            return redirect(is_string($redirectUrl) ? $redirectUrl : '/sign-in/factor-two');
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
