<?php

namespace blt950\OauthGeneric;

use blt950\OauthGeneric\Avatar\AvatarFromUrl;
use Flarum\User\LoginProvider;
use FoF\OAuth\Controllers\AuthController;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
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

    public function boot(): void
    {
        // When a new user is created via OAuth, FoF creates LoginProvider after the user.
        // We cached the avatar URL in Generic::suggestions() by identifier; sync it here.
        $this->app->make('events')->listen(
            'eloquent.created: ' . LoginProvider::class,
            function (LoginProvider $model): void {
                if ($model->provider !== 'generic') {
                    return;
                }
                $cacheKey = 'blt950_oauth_avatar_' . $model->identifier;
                $avatarUrl = Cache::get($cacheKey);
                if ($avatarUrl === null || $avatarUrl === '') {
                    return;
                }
                Cache::forget($cacheKey);
                $user = $model->user;
                if ($user === null) {
                    return;
                }
                $this->app->make(AvatarFromUrl::class)->syncFromUrl($user, $avatarUrl);
            }
        );
    }
}
