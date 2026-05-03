<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Publishable / Secret keys
    |--------------------------------------------------------------------------
    |
    | The publishable key is the one you embed in browser-side code; it encodes
    | the Frontend API host, so the JWT verifier can derive the JWKS URL
    | from it without extra config. The secret key is server-only and signs
    | every Backend API request.
    |
    */

    'publishable_key' => env('AUTHN_PUBLISHABLE_KEY'),

    'secret_key' => env('AUTHN_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API URL overrides
    |--------------------------------------------------------------------------
    |
    | Both default to the production hosts (api.authn.sh / derived FAPI URL)
    | when left blank. Set these for self-hosted deployments or when pointing
    | a single Laravel app at a non-production environment.
    |
    */

    'api_url' => env('AUTHN_API_URL'),

    'frontend_api_url' => env('AUTHN_FRONTEND_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | Webhook signing
    |--------------------------------------------------------------------------
    |
    | `webhook_signing_secret` is the active signing secret (whsec_…). During
    | a rotation overlap, add the previous secret(s) to `webhook_signing_secrets`
    | so the verifier accepts both. `webhook_tolerance` is the replay window
    | in seconds (defaults to 5 minutes).
    |
    */

    'webhook_signing_secret' => env('AUTHN_WEBHOOK_SECRET'),

    'webhook_signing_secrets' => array_values(array_filter([
        // 'whsec_old_xxxx',
    ])),

    'webhook_tolerance' => (int) env('AUTHN_WEBHOOK_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | JWT verifier
    |--------------------------------------------------------------------------
    |
    | The Frontend API JWKS document is cached in Laravel's cache for
    | `jwks_cache_ttl` seconds. Leave `jwks_cache_store` null to use the
    | default cache store, or name a specific store ("redis", "memcached", …).
    | `allowed_clock_skew` is applied when validating exp / iat / nbf.
    |
    */

    'jwks_cache_ttl' => (int) env('AUTHN_JWKS_CACHE_TTL', 600),

    'jwks_cache_store' => env('AUTHN_JWKS_CACHE_STORE'),

    'allowed_clock_skew' => (int) env('AUTHN_ALLOWED_CLOCK_SKEW', 5),

];
