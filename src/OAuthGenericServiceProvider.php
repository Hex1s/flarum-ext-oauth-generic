<?php

namespace blt950\OauthGeneric;

use FoF\OAuth\Controllers\AuthController;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageManager;

class OAuthGenericServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Use our controller so OAuth runs in same tab (display=page), fixing existing-user re-login.
        $this->app->bind(
            AuthController::class,
            Controllers\GenericAuthController::class
        );

        // Avatar sync from URL: ensure HTTP client and Image manager are available for AvatarFromUrl.
        $this->app->bind(Client::class, function () {
            return new Client();
        });
        $this->app->bind(ImageManager::class, function () {
            return new ImageManager();
        });
    }
}
