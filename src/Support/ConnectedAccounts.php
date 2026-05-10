<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Support;

use Authn\Sdk\Tokens\VerifiedClaims;

/**
 * Reads the user's connected `ExternalAccount` rows out of a `VerifiedClaims`
 * snapshot. Until the FAPI's session-token issuer surfaces an `external_accounts`
 * snapshot in the JWT (tracked server-side), the helper falls back to scanning
 * the raw claim bag for either of the agreed keys: `eac` or `external_accounts`.
 * Both forms are accepted as a list of objects with a `provider` field that
 * matches `OauthProvider.provider_key`.
 */
final class ConnectedAccounts
{
    /**
     * @return list<string>
     */
    public static function providerKeys(?VerifiedClaims $claims): array
    {
        if ($claims === null) {
            return [];
        }

        $rows = $claims->raw['external_accounts'] ?? $claims->raw['eac'] ?? null;
        if (! is_array($rows)) {
            return [];
        }

        $keys = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $keys[] = $row;

                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $provider = $row['provider'] ?? $row['p'] ?? null;
            if (is_string($provider) && $provider !== '') {
                $keys[] = $provider;
            }
        }

        return array_values(array_unique($keys));
    }

    public static function has(?VerifiedClaims $claims, string $providerKey): bool
    {
        return in_array($providerKey, self::providerKeys($claims), true);
    }
}
