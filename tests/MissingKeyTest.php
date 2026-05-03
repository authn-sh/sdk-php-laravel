<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Tokens\TokenVerifier;
use Authn\Sdk\Webhooks\SignatureVerifier;

it('explains how to fix a missing AUTHN_SECRET_KEY', function (): void {
    config()->set('authn.secret_key', null);

    expect(fn () => app(Client::class))
        ->toThrow(RuntimeException::class, 'AUTHN_SECRET_KEY');
});

it('explains how to fix a missing AUTHN_PUBLISHABLE_KEY', function (): void {
    config()->set('authn.publishable_key', null);

    expect(fn () => app(TokenVerifier::class))
        ->toThrow(RuntimeException::class, 'AUTHN_PUBLISHABLE_KEY');
});

it('explains how to fix a missing AUTHN_WEBHOOK_SECRET', function (): void {
    config()->set('authn.webhook_signing_secret', null);
    config()->set('authn.webhook_signing_secrets', []);

    expect(fn () => app(SignatureVerifier::class))
        ->toThrow(RuntimeException::class, 'AUTHN_WEBHOOK_SECRET');
});

it('treats whitespace-only keys as missing', function (): void {
    config()->set('authn.secret_key', '   ');

    expect(fn () => app(Client::class))
        ->toThrow(RuntimeException::class, 'AUTHN_SECRET_KEY');
});

it('accepts a rotation list when the primary secret is unset', function (): void {
    config()->set('authn.webhook_signing_secret', null);
    config()->set('authn.webhook_signing_secrets', ['whsec_old', 'whsec_older']);

    expect(app(SignatureVerifier::class))->toBeInstanceOf(SignatureVerifier::class);
});
