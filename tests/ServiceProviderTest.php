<?php

declare(strict_types=1);

use Authn\Sdk\Laravel\AuthnServiceProvider;

it('registers the service provider when the test app boots', function (): void {
    expect(app()->getLoadedProviders())->toHaveKey(AuthnServiceProvider::class);
});

it('boots the test app without throwing', function (): void {
    expect(app()->isBooted())->toBeTrue();
});
