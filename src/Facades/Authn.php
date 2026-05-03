<?php

declare(strict_types=1);

namespace Authn\Sdk\Laravel\Facades;

use Authn\Sdk\Laravel\AuthnManager;
use Illuminate\Support\Facades\Facade;

/**
 * Static-proxy facade for the authn.sh Laravel package.
 *
 * Resolves to the singleton-bound `\Authn\Sdk\Laravel\AuthnManager` so calls
 * like `Authn::client()` and `Authn::auth()` reach the manager statically.
 *
 * @method static \Authn\Sdk\Client client()
 * @method static \Authn\Sdk\Tokens\TokenVerifier tokens()
 * @method static \Authn\Sdk\Webhooks\SignatureVerifier webhooks()
 * @method static \Authn\Sdk\Tokens\VerifiedClaims|null auth()
 * @method static \Authn\Sdk\Resources\UsersManager users()
 * @method static \Authn\Sdk\Resources\SessionsManager sessions()
 * @method static \Authn\Sdk\Resources\InvitationsManager invitations()
 * @method static \Authn\Sdk\Resources\AllowlistIdentifiersManager allowlistIdentifiers()
 * @method static \Authn\Sdk\Resources\BlocklistIdentifiersManager blocklistIdentifiers()
 * @method static \Authn\Sdk\Resources\RedirectUrlsManager redirectUrls()
 * @method static \Authn\Sdk\Resources\InstanceManager instance()
 * @method static \Authn\Sdk\Resources\WebhookEndpointsManager webhookEndpoints()
 *
 * @see AuthnManager
 */
class Authn extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'authn.manager';
    }
}
