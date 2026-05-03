<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Laravel\AuthnManager;
use Authn\Sdk\Tokens\TokenVerifier;
use Authn\Sdk\Webhooks\SignatureVerifier;

it('binds the BAPI Client as a singleton', function (): void {
    $first = app(Client::class);
    $second = app(Client::class);

    expect($first)->toBeInstanceOf(Client::class)
        ->and($first)->toBe($second);
});

it('binds the TokenVerifier as a singleton', function (): void {
    $first = app(TokenVerifier::class);
    $second = app(TokenVerifier::class);

    expect($first)->toBeInstanceOf(TokenVerifier::class)
        ->and($first)->toBe($second);
});

it('binds the SignatureVerifier as a singleton', function (): void {
    $first = app(SignatureVerifier::class);
    $second = app(SignatureVerifier::class);

    expect($first)->toBeInstanceOf(SignatureVerifier::class)
        ->and($first)->toBe($second);
});

it('binds the AuthnManager + facade accessor', function (): void {
    /** @var AuthnManager $byKey */
    $byKey = app('authn.manager');
    $byClass = app(AuthnManager::class);

    expect($byKey)->toBeInstanceOf(AuthnManager::class);
    expect($byKey)->toBe($byClass);
});

it('exposes string aliases for each SDK service', function (): void {
    /** @var Client $client */
    $client = app('authn.client');
    /** @var TokenVerifier $tokens */
    $tokens = app('authn.tokens');
    /** @var SignatureVerifier $webhooks */
    $webhooks = app('authn.webhooks');

    expect($client)->toBe(app(Client::class));
    expect($tokens)->toBe(app(TokenVerifier::class));
    expect($webhooks)->toBe(app(SignatureVerifier::class));
});

it('configures Client with the api_url override when provided', function (): void {
    config()->set('authn.api_url', 'https://staging.api.authn.sh');

    /** @var Client $client */
    $client = app(Client::class);

    expect($client->config()->apiUrl)->toBe('https://staging.api.authn.sh');
});
