<?php

namespace blt950\OauthGeneric;

use blt950\OauthGeneric\Avatar\AvatarFromUrl;
use Flarum\User\AvatarUploader;
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

        // Avatar sync from URL: bind dependencies and AvatarFromUrl explicitly to avoid
        // deep/circular resolution that can exhaust memory in Container.
        $this->app->bind(Client::class, function () {
            return new Client();
        });
        $this->app->bind(ImageManager::class, function () {
            return new ImageManager();
        });
        $this->app->bind(AvatarFromUrl::class, function ($app) {
            $logger = $app->bound('log') ? $app->make('log') : null;
            return new AvatarFromUrl(
                $app->make(AvatarUploader::class),
                $app->make(Client::class),
                $app->make(ImageManager::class),
                $logger
            );
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
                \error_log('[blt950_oauth] LoginProvider created provider=generic identifier=' . $model->identifier);
                try {
                    $cacheKey = 'blt950_oauth_avatar_' . $model->identifier;
                    $avatarUrl = Cache::get($cacheKey);
                    if ($avatarUrl === null || $avatarUrl === '') {
                        \error_log('[blt950_oauth] No cached avatar for identifier=' . $model->identifier . ' (cache miss or expired)');
                        return;
                    }
                    Cache::forget($cacheKey);
                    $user = $model->user;
                    if ($user === null) {
                        \error_log('[blt950_oauth] LoginProvider has no user relation');
                        return;
                    }
                    \error_log('[blt950_oauth] Syncing avatar from URL for user id=' . $user->id);
                    $this->app->make(AvatarFromUrl::class)->syncFromUrl($user, $avatarUrl);
                } catch (\Throwable $e) {
                    \error_log('[blt950_oauth] Avatar sync error: ' . $e->getMessage());
                }
            }
        );
    }
}
