<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Http\Middleware;

use Authn\Sdk\Laravel\Support\ConnectedAccounts;
use Authn\Sdk\Tokens\VerifiedClaims;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class RequiresConnectedAccount
{
    public function handle(Request $request, Closure $next, ?string $providerKey = null): Response
    {
        if ($providerKey === null || $providerKey === '') {
            throw new InvalidArgumentException(
                'authn.connected: provider_key parameter is required (e.g. authn.connected:google).',
            );
        }

        $claims = $request->attributes->get(AuthenticateWithAuthn::REQUEST_ATTRIBUTE);

        if (! $claims instanceof VerifiedClaims) {
            $signInUrl = config('authn.url.sign_in');

            return redirect(is_string($signInUrl) ? $signInUrl : '/sign-in');
        }

        if (ConnectedAccounts::has($claims, $providerKey)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $template = config('authn.connected_accounts.redirect_url');
        $template = is_string($template) && $template !== ''
            ? $template
            : '/sign-in/sso-callback?provider={provider}';

        return redirect(str_replace('{provider}', rawurlencode($providerKey), $template));
    }
}
