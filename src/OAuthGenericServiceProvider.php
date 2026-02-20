<?php

namespace blt950\OauthGeneric;

use FoF\OAuth\Controllers\AuthController;
use Illuminate\Support\ServiceProvider;

class OAuthGenericServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Use our controller so OAuth runs in same tab (display=page), fixing existing-user re-login.
        $this->app->bind(
            AuthController::class,
            Controllers\GenericAuthController::class
        );
    }
}
