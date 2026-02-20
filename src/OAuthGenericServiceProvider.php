<?php

namespace blt950\OauthGeneric;

use FoF\OAuth\Controllers\AuthController;
use Illuminate\Contracts\Container\Container;

class OAuthGenericServiceProvider
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function register(): void
    {
        // Use our controller so OAuth runs in same tab (display=page), fixing existing-user re-login.
        $this->container->bind(
            AuthController::class,
            Controllers\GenericAuthController::class
        );
    }
}
