<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Support;

use Authn\Sdk\Tokens\VerifiedClaims;

/**
 * Reads the user's `AuthorizationGrant` state out of a `VerifiedClaims`
 * snapshot — driving the `@authnHasAuthorizedApp` Blade directive and the
 * `Authn::hasAuthorizedApp($appId)` facade helper.
 *
 * v0.7 ships forward-compat: consumers register the directive against an
 * `OauthApplication.id` (`oac_*`) and the helper resolves to `false` for
 * every grant until the server emits the `authorized_apps[]` claim on
 * session JWTs (tracked as a v0.8 follow-up against the `authn` repo). The
 * directive API surface is stable across that transition — once the claim
 * lands, the same templates start gating correctly without consumer
 * changes.
 *
 * The claim shape, when emitted, is a flat list of `OauthApplication.id`
 * strings (the row id `oac_*`, not the public `oac_pub_*` client id), one
 * entry per active grant for the active session's user. Revoked grants are
 * filtered out at issue time, so a present id always means an active
 * grant.
 */
final class AuthorizedApps
{
    /**
     * True iff the verified claim set carries an active `AuthorizationGrant`
     * for `$appId`. Returns `false` for null claims and for any claim set
     * that doesn't yet carry the `authorized_apps` entry (the v0.7 default).
     */
    public static function has(?VerifiedClaims $claims, string $appId): bool
    {
        if ($claims === null || $appId === '') {
            return false;
        }

        return in_array($appId, self::granted($claims), true);
    }

    /**
     * Returns every `OauthApplication.id` the verified user has actively
     * granted to the active session. Empty list when the claim is absent
     * or malformed. Order matches the server emission; duplicates are
     * preserved (the server is responsible for de-duping at mint time).
     *
     * @return list<string>
     */
    public static function granted(?VerifiedClaims $claims): array
    {
        if ($claims === null) {
            return [];
        }

        $value = $claims->customClaim('authorized_apps');
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }
}
