# Changelog

## [0.2.0] — 2026-05-10

### Added

- `@authnHas('role:org:admin' | 'permission:org:foo:bar')` Blade directive — functional implementation backed by `VerifiedClaims->organization` (sdk-php SP-3). Invalid prefixes raise an `InvalidArgumentException` at render time so typos don't silently fall through.
- `Authn::hasRole(string $key)` and `Authn::hasPermission(string $key)` facade proxy methods on `AuthnManager`.

### Changed

- `authn-sh/sdk-php` constraint pinned to `^0.2`.

## [0.1.0] — 2026-05-03

Initial release: `AuthnServiceProvider`, `AuthnManager`, `Authn` facade, `AuthenticateWithAuthn` middleware, Blade directives `@authnSignedIn` / `@authnSignedOut` / `@authnHas` (stub).
