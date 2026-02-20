<?php

/*
 * Sync avatar from OAuth provider on login (existing user) and when user is created (new user).
 */

namespace blt950\OauthGeneric\Listeners;

use blt950\OauthGeneric\Avatar\AvatarFromUrl;
use Flarum\User\Event\Registered as UserRegistered;
use Flarum\User\LoginProvider;
use FoF\Extend\Events\OAuthLoginSuccessful;

class SyncOAuthAvatarListener
{
    public function __construct(
        protected AvatarFromUrl $avatarFromUrl
    ) {
    }

    /**
     * When existing user logs in via OAuth — sync avatar from provider.
     */
    public function onOAuthLoginSuccessful(OAuthLoginSuccessful $event): void
    {
        if ($event->providerName !== 'generic') {
            return;
        }

        $loginProvider = LoginProvider::where('provider', $event->providerName)
            ->where('identifier', $event->identifier)
            ->first();

        if (! $loginProvider || ! $loginProvider->user) {
            return;
        }

        $avatarUrl = null;
        if (method_exists($event->userResource, 'getAvatar')) {
            $avatarUrl = $event->userResource->getAvatar();
        }

        $this->avatarFromUrl->syncFromUrl($loginProvider->user, $avatarUrl);
    }

    /**
     * When user is registered (e.g. from OAuth registration token) with avatar_url as URL — download and save.
     */
    public function onUserRegistered(UserRegistered $event): void
    {
        $user = $event->user;
        $avatarUrl = $user->avatar_url;

        if ($avatarUrl === null || $avatarUrl === '') {
            return;
        }

        if (substr($avatarUrl, 0, 7) !== 'http://' && substr($avatarUrl, 0, 8) !== 'https://') {
            return;
        }

        $this->avatarFromUrl->syncFromUrl($user, $avatarUrl);
    }
}
