<?php

declare(strict_types=1);

use Authn\Sdk\Tokens\TokenVerifier;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\CacheInterface;

function cachePropertyOf(TokenVerifier $verifier): CacheInterface
{
    $reflection = new ReflectionClass($verifier);
    $prop = $reflection->getProperty('cache');
    $prop->setAccessible(true);

    /** @var CacheInterface $cache */
    $cache = $prop->getValue($verifier);

    return $cache;
}

it('wires the default cache store into TokenVerifier as PSR-16', function (): void {
    /** @var TokenVerifier $verifier */
    $verifier = app(TokenVerifier::class);

    $cache = cachePropertyOf($verifier);

    expect($cache)->toBeInstanceOf(CacheInterface::class);
    expect($cache)->toBe(Cache::store());
});

it('honours jwks_cache_store when a specific store is configured', function (): void {
    config()->set('cache.stores.jwks_test_store', ['driver' => 'array', 'serialize' => false]);
    config()->set('authn.jwks_cache_store', 'jwks_test_store');

    /** @var TokenVerifier $verifier */
    $verifier = app(TokenVerifier::class);

    expect(cachePropertyOf($verifier))->toBe(Cache::store('jwks_test_store'));
});
