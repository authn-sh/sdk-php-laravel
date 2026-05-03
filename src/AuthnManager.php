<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel;

use Authn\Sdk\Client;
use Authn\Sdk\Resources\AllowlistIdentifiersManager;
use Authn\Sdk\Resources\BlocklistIdentifiersManager;
use Authn\Sdk\Resources\InstanceManager;
use Authn\Sdk\Resources\InvitationsManager;
use Authn\Sdk\Resources\RedirectUrlsManager;
use Authn\Sdk\Resources\SessionsManager;
use Authn\Sdk\Resources\UsersManager;
use Authn\Sdk\Resources\WebhookEndpointsManager;
use Authn\Sdk\Tokens\TokenVerifier;
use Authn\Sdk\Tokens\VerifiedClaims;
use Authn\Sdk\Webhooks\SignatureVerifier;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Single entry point used by the `Authn` facade. Lazily resolves the underlying
 * SDK objects from the container so they're not all built unless you call them.
 *
 * Also stores the optional `User` resolver: a closure that maps a verified set
 * of claims onto an `Authenticatable` so `Auth::user()` returns the customer's
 * own model after AuthenticateWithAuthn runs.
 */
class AuthnManager
{
    /** @var (callable(VerifiedClaims): ?Authenticatable)|null */
    private $userResolver = null;

    public function __construct(private readonly Container $container) {}

    public function client(): Client
    {
        /** @var Client $client */
        $client = $this->container->make(Client::class);

        return $client;
    }

    public function tokens(): TokenVerifier
    {
        /** @var TokenVerifier $verifier */
        $verifier = $this->container->make(TokenVerifier::class);

        return $verifier;
    }

    public function webhooks(): SignatureVerifier
    {
        /** @var SignatureVerifier $verifier */
        $verifier = $this->container->make(SignatureVerifier::class);

        return $verifier;
    }

    /**
     * Verified claims for the current request, after the AuthenticateWithAuthn
     * middleware (SPL-3) has run. Returns null outside of an authenticated
     * request lifecycle.
     */
    public function auth(): ?VerifiedClaims
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        $request = $this->container->make('request');
        if (! $request instanceof Request) {
            return null;
        }

        $claims = $request->attributes->get('authn.claims');

        return $claims instanceof VerifiedClaims ? $claims : null;
    }

    /**
     * Register a callable that maps verified claims to an `Authenticatable`.
     *
     * The resolver runs inside the AuthenticateWithAuthn middleware. Pass `null`
     * to clear a previously-registered resolver.
     *
     * @param  (callable(VerifiedClaims): ?Authenticatable)|null  $resolver
     */
    public function resolveUserUsing(?callable $resolver): void
    {
        $this->userResolver = $resolver;
    }

    /**
     * @return (callable(VerifiedClaims): ?Authenticatable)|null
     */
    public function getUserResolver(): ?callable
    {
        return $this->userResolver;
    }

    public function users(): UsersManager
    {
        return $this->client()->users();
    }

    public function sessions(): SessionsManager
    {
        return $this->client()->sessions();
    }

    public function invitations(): InvitationsManager
    {
        return $this->client()->invitations();
    }

    public function allowlistIdentifiers(): AllowlistIdentifiersManager
    {
        return $this->client()->allowlistIdentifiers();
    }

    public function blocklistIdentifiers(): BlocklistIdentifiersManager
    {
        return $this->client()->blocklistIdentifiers();
    }

    public function redirectUrls(): RedirectUrlsManager
    {
        return $this->client()->redirectUrls();
    }

    public function instance(): InstanceManager
    {
        return $this->client()->instance();
    }

    public function webhookEndpoints(): WebhookEndpointsManager
    {
        return $this->client()->webhookEndpoints();
    }
}
