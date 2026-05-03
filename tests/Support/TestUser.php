<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Minimal Authenticatable used by middleware tests to verify the User-resolver
 * hook calls Auth::guard()->setUser(...).
 */
final class TestUser implements Authenticatable
{
    public function __construct(
        public readonly string $id,
        public readonly string $email = '',
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
