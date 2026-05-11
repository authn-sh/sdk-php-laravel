<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Http\Middleware;

use Authn\Sdk\Laravel\Support\Passkeys;
use Authn\Sdk\Tokens\VerifiedClaims;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class RequiresPasskey
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $claims = $request->attributes->get(AuthenticateWithAuthn::REQUEST_ATTRIBUTE);

        if (! $claims instanceof VerifiedClaims) {
            $signInUrl = config('authn.url.sign_in');

            return redirect(is_string($signInUrl) ? $signInUrl : '/sign-in');
        }

        $resolvedMode = $mode ?? $this->configuredDefaultMode();

        if (! Passkeys::matches($claims, $resolvedMode)) {
            $enrollUrl = config('authn.passkey.enroll_url');

            return redirect(is_string($enrollUrl) && $enrollUrl !== ''
                ? $enrollUrl
                : '/user/security/passkeys');
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    private function configuredDefaultMode(): string
    {
        $configured = config('authn.passkey.default_strict_mode');
        if (! is_string($configured) || $configured === '') {
            return Passkeys::MODE_VERIFIED;
        }

        if (! in_array($configured, [Passkeys::MODE_VERIFIED, Passkeys::MODE_ENROLLED], true)) {
            throw new InvalidArgumentException(
                "authn.passkey.default_strict_mode: unrecognised mode \"{$configured}\" — must be \"verified\" or \"enrolled\".",
            );
        }

        return $configured;
    }
}
