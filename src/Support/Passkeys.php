<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Support;

use Authn\Sdk\Tokens\VerifiedClaims;
use InvalidArgumentException;

final class Passkeys
{
    public const MODE_VERIFIED = 'verified';

    public const MODE_ENROLLED = 'enrolled';

    public static function matches(?VerifiedClaims $claims, string $mode): bool
    {
        if ($claims === null) {
            return false;
        }

        return match ($mode) {
            self::MODE_VERIFIED => $claims->wasVerifiedByPasskey(),
            self::MODE_ENROLLED => $claims->hasPasskey(),
            default => throw new InvalidArgumentException(
                "authn passkey mode: unrecognised mode \"{$mode}\" — must be \"verified\" or \"enrolled\".",
            ),
        };
    }
}
