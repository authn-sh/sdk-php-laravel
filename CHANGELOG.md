# Changelog

## [0.6.0] — 2026-05-12

### Added

- `@authnHasEnterpriseAccount[(<connection_id>)]` Blade directive. Renders the inner block based on the active session's enterprise-SSO signal. With no argument, applies the configured default strict mode (`verified` — current session was minted through an enterprise IdP; or `linked` — user has at least one linked `EnterpriseAccount`). With a connection-id argument, matches when the current session's `entcon` equals it OR the user has a linked `EnterpriseAccount` for that connection.
- `Authn\Sdk\Laravel\Http\Middleware\RequiresEnterpriseSso` route middleware. Aliased as `authn.requires_enterprise_sso[:<mode>]`. Fail-closes by redirecting to `authn.url.sign_in` for unauthenticated requests and to `authn.enterprise_sso.redirect_url` for authenticated requests lacking the required enterprise-SSO signal.
- `Authn\Sdk\Laravel\Support\EnterpriseAccounts` helper exposing `matches(?VerifiedClaims, string $mode): bool`, `hasConnection(?VerifiedClaims, $connectionId): bool`, `connectionIds(?VerifiedClaims): list<string>` and the `MODE_VERIFIED` / `MODE_LINKED` constants. Reads enterprise-account snapshots from the JWT raw claim bag (`enterprise_accounts[]` or `eac[]`, both objects with `enterprise_connection_id` and bare strings accepted).
- `Authn::hasEnterpriseSso(?string $mode = null)` facade helper. Honours the configured default strict mode when no argument is supplied.
- `authn.enterprise_sso.redirect_url` config key (env: `AUTHN_ENTERPRISE_SSO_REDIRECT_URL`, default `/sign-in/enterprise-sso`).
- `authn.enterprise_sso.default_strict_mode` config key (env: `AUTHN_ENTERPRISE_SSO_DEFAULT_STRICT_MODE`, default `verified`).

### Changed

- Composer constraint on `authn-sh/sdk-php` tightened to `^0.6` (was `dev-main || ^0.5`). Drops the dev-only VCS repository entry, the `minimum-stability: dev` workaround, and the matching "Pin authn-sh/sdk-php to dev-main" CI step — all of which bridged the v0.6 alpha cycle. `sdk-php@v0.6.0` is now on Packagist.

### Changed

- Composer constraint on `authn-sh/sdk-php` widened to `dev-main || ^0.5` so CI can resolve against sdk-php main during the v0.6 alpha cycle. The VCS repository entry, `minimum-stability: dev`, the "Pin authn-sh/sdk-php to dev-main (v0.6 integration)" CI step, and the `0.6.x-dev` branch alias are restored. All four are torn down again when the release dance cuts v0.6.0.

## [0.5.0] — 2026-05-11

### Added

- `@authnHasPasskey[(<mode>)]` Blade directive. Renders the inner block based on the active session's passkey signal. Two modes: `verified` (default — matches when the current session was authenticated by a passkey first-factor) and `enrolled` (matches whenever the user has at least one verified passkey on file).
- `Authn\Sdk\Laravel\Http\Middleware\RequiresPasskey` route middleware. Aliased as `authn.requires_passkey[:<mode>]`. Fail-closes by redirecting to `authn.url.sign_in` for unauthenticated requests and to `authn.passkey.enroll_url` for authenticated requests lacking the required passkey signal.
- `Authn\Sdk\Laravel\Support\Passkeys` helper exposing `matches(?VerifiedClaims, string $mode): bool` and the `MODE_VERIFIED` / `MODE_ENROLLED` constants.
- `Authn::hasPasskey(?string $mode = null)` facade helper. Honours the configured default strict mode when no argument is supplied.
- `authn.passkey.enroll_url` config key (env: `AUTHN_PASSKEY_ENROLL_URL`, default `/user/security/passkeys`).
- `authn.passkey.default_strict_mode` config key (env: `AUTHN_PASSKEY_DEFAULT_STRICT_MODE`, default `verified`).

### Changed

- Composer constraint on `authn-sh/sdk-php` tightened to `^0.5` (was `dev-main || ^0.4`). Drops the dev-only VCS repository entry, the `minimum-stability: dev` workaround, and the matching "Pin authn-sh/sdk-php to dev-main" CI step — all of which bridged the v0.5 alpha cycle. `sdk-php@v0.5.0` is now on Packagist.

## [0.4.0] — 2026-05-11

### Added

- `@authnHasConnectedAccount('<provider_key>')` Blade directive. Renders the inner block when the active session JWT advertises an `ExternalAccount` linked to that provider.
- `Authn\Sdk\Laravel\Http\Middleware\RequiresConnectedAccount` route middleware. Aliased as `authn.connected:<provider_key>`. Redirects to `authn.connected_accounts.redirect_url` (default `/sign-in/sso-callback?provider={provider}`) when the linked account is missing, and to `authn.url.sign_in` for unauthenticated requests.
- `Authn\Sdk\Laravel\Support\ConnectedAccounts` helper exposing `providerKeys(VerifiedClaims)` and `has(VerifiedClaims, $providerKey)`. Reads `external_accounts[]` / `eac[]` from the JWT raw claim bag — both compact (`{p: "google"}` or string) and expanded (`{provider: "google", external_id: …}`) shapes are accepted.
- `authn.connected_accounts.redirect_url` config key (env: `AUTHN_CONNECTED_REDIRECT_URL`, default `/sign-in/sso-callback?provider={provider}`).

### Changed

- `authn-sh/sdk-php` constraint tightened to `^0.4` (was `dev-main || ^0.4`). Drops the dev-only VCS repository entry + `minimum-stability: dev` workaround that bridged the v0.4 alpha cycle.

## [0.3.0] — 2026-05-10

### Added

- `@authnRequiresMfa` Blade directive — fail-close check that the verified `__session` JWT carries `twoFactorVerified === true`.
- `RequiresMfa` route middleware (alias `authn.requires_mfa`) — fail-close redirect to `/sign-in/factor-two` when the session lacks a recent second-factor proof. Configurable freshness window via `config('authn.mfa.max_age_seconds')` (default 1800) or per-route override (`'authn.requires_mfa:300'`).
- `config('authn.mfa.redirect_url')` — Account Portal factor-two URL (default `/sign-in/factor-two`).
- `Authn::hasMfa()` / `Authn::secondFactorAgeSeconds()` facade helpers.

### Changed

- `authn-sh/sdk-php` constraint pinned to `^0.3` (was `dev-main || ^0.2`). Drops the dev-only VCS repository entry + `minimum-stability: dev` workaround that bridged the v0.2 alpha cycle.

## [0.2.0] — 2026-05-10

### Added

- `@authnHas('role:org:admin' | 'permission:org:foo:bar')` Blade directive — functional implementation backed by `VerifiedClaims->organization` (sdk-php SP-3). Invalid prefixes raise an `InvalidArgumentException` at render time so typos don't silently fall through.
- `Authn::hasRole(string $key)` and `Authn::hasPermission(string $key)` facade proxy methods on `AuthnManager`.

### Changed

- `authn-sh/sdk-php` constraint pinned to `^0.2`.

## [0.1.0] — 2026-05-03

Initial release: `AuthnServiceProvider`, `AuthnManager`, `Authn` facade, `AuthenticateWithAuthn` middleware, Blade directives `@authnSignedIn` / `@authnSignedOut` / `@authnHas` (stub).
