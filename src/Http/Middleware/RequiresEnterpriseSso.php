<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Http\Middleware;

use Authn\Sdk\Laravel\Support\EnterpriseAccounts;
use Authn\Sdk\Tokens\VerifiedClaims;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class RequiresEnterpriseSso
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $claims = $request->attributes->get(AuthenticateWithAuthn::REQUEST_ATTRIBUTE);

        if (! $claims instanceof VerifiedClaims) {
            $signInUrl = config('authn.url.sign_in');

            return redirect(is_string($signInUrl) ? $signInUrl : '/sign-in');
        }

        $resolvedMode = $mode ?? $this->configuredDefaultMode();

        if (! EnterpriseAccounts::matches($claims, $resolvedMode)) {
            $redirectUrl = config('authn.enterprise_sso.redirect_url');

            return redirect(is_string($redirectUrl) && $redirectUrl !== ''
                ? $redirectUrl
                : '/sign-in/enterprise-sso');
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    private function configuredDefaultMode(): string
    {
        $configured = config('authn.enterprise_sso.default_strict_mode');
        if (! is_string($configured) || $configured === '') {
            return EnterpriseAccounts::MODE_VERIFIED;
        }

        if (! in_array($configured, [EnterpriseAccounts::MODE_VERIFIED, EnterpriseAccounts::MODE_LINKED], true)) {
            throw new InvalidArgumentException(
                "authn.enterprise_sso.default_strict_mode: unrecognised mode \"{$configured}\" — must be \"verified\" or \"linked\".",
            );
        }

        return $configured;
    }
}
