<?php

namespace blt950\OauthGeneric;

use FoF\OAuth\Controllers\AuthController;
use Illuminate\Contracts\Container\Container;

class OAuthGenericServiceProvider
{
    public function register(Container $container): void
    {
        // Use our controller so OAuth runs in same tab (display=page), fixing existing-user re-login.
        $container->bind(
            AuthController::class,
            Controllers\GenericAuthController::class
        );
    }
}
