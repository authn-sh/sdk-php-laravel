<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('publishes config/authn.php under the authn-config tag', function (): void {
    $target = config_path('authn.php');
    if (File::exists($target)) {
        File::delete($target);
    }

    Artisan::call('vendor:publish', ['--tag' => 'authn-config', '--force' => true]);

    expect(File::exists($target))->toBeTrue();
    /** @var array<string, mixed> $published */
    $published = require $target;
    expect($published)->toHaveKey('publishable_key')
        ->toHaveKey('secret_key')
        ->toHaveKey('webhook_signing_secret')
        ->toHaveKey('jwks_cache_ttl');

    File::delete($target);
});
