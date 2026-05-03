<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Laravel\Facades\Authn;
use Authn\Sdk\Resources\InstanceManager;
use Authn\Sdk\Resources\InvitationsManager;
use Authn\Sdk\Resources\SessionsManager;
use Authn\Sdk\Resources\UsersManager;
use Authn\Sdk\Tokens\TokenVerifier;
use Authn\Sdk\Webhooks\SignatureVerifier;

it('routes core service calls through the bound AuthnManager', function (): void {
    expect(Authn::client())->toBeInstanceOf(Client::class)
        ->and(Authn::client())->toBe(app(Client::class));

    expect(Authn::tokens())->toBeInstanceOf(TokenVerifier::class)
        ->and(Authn::tokens())->toBe(app(TokenVerifier::class));

    expect(Authn::webhooks())->toBeInstanceOf(SignatureVerifier::class)
        ->and(Authn::webhooks())->toBe(app(SignatureVerifier::class));
});

it('exposes resource manager passthroughs', function (): void {
    expect(Authn::users())->toBeInstanceOf(UsersManager::class);
    expect(Authn::sessions())->toBeInstanceOf(SessionsManager::class);
    expect(Authn::invitations())->toBeInstanceOf(InvitationsManager::class);
    expect(Authn::instance())->toBeInstanceOf(InstanceManager::class);
});

it('returns null from auth() when no middleware has populated the request attributes', function (): void {
    expect(Authn::auth())->toBeNull();
});
