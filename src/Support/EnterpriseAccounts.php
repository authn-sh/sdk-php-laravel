<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Support;

use Authn\Sdk\Tokens\VerifiedClaims;
use InvalidArgumentException;

/**
 * Reads the user's enterprise-SSO state out of a `VerifiedClaims` snapshot.
 *
 * Two modes:
 *
 * - `verified` — the *current session* was authenticated by an enterprise
 *   IdP (the JWT carries the `entcon` claim emitted by AU-16).
 * - `linked` — the user has **at least one** `EnterpriseAccount` linked, even
 *   if the current session was minted via a different strategy. Until the
 *   FAPI session-token issuer adds an `enterprise_accounts` snapshot to the
 *   JWT, this falls back to scanning the raw claim bag for `enterprise_accounts`
 *   or the short-form key `eac` (list of objects with `enterprise_connection_id`
 *   keys, or plain strings carrying the connection id). A populated `entcon`
 *   claim also counts as linked.
 */
final class EnterpriseAccounts
{
    public const MODE_VERIFIED = 'verified';

    public const MODE_LINKED = 'linked';

    public static function matches(?VerifiedClaims $claims, string $mode): bool
    {
        if ($claims === null) {
            return false;
        }

        return match ($mode) {
            self::MODE_VERIFIED => $claims->wasVerifiedByEnterpriseSso(),
            self::MODE_LINKED => $claims->wasVerifiedByEnterpriseSso() || self::connectionIds($claims) !== [],
            default => throw new InvalidArgumentException(
                "authn enterprise SSO mode: unrecognised mode \"{$mode}\" — must be \"verified\" or \"linked\".",
            ),
        };
    }

    /**
     * True iff the active session's `enterpriseConnectionId` equals
     * `$connectionId`, or the user has a linked `EnterpriseAccount` for it.
     */
    public static function hasConnection(?VerifiedClaims $claims, string $connectionId): bool
    {
        if ($claims === null) {
            return false;
        }

        if ($claims->enterpriseConnectionId === $connectionId) {
            return true;
        }

        return in_array($connectionId, self::connectionIds($claims), true);
    }

    /**
     * @return list<string>
     */
    public static function connectionIds(?VerifiedClaims $claims): array
    {
        if ($claims === null) {
            return [];
        }

        $rows = $claims->raw['enterprise_accounts'] ?? $claims->raw['eac'] ?? null;
        if (! is_array($rows)) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            if (is_string($row) && $row !== '') {
                $ids[] = $row;

                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $id = $row['enterprise_connection_id'] ?? $row['ec'] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
