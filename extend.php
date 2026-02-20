<?php

/*
 * This file is part of blt950/oauth-generic.
 *
 * Copyright (c) Blt950.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace blt950\OauthGeneric;

use Flarum\Extend;
use Flarum\User\Event\Registered as UserRegistered;
use FoF\Extend\Events\OAuthLoginSuccessful;
use FoF\OAuth\Extend as OAuthExtend;

return [
    (new Extend\Frontend('forum'))
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new OAuthExtend\RegisterProvider(Providers\Generic::class)),

    (new Extend\ServiceProvider())
        ->register(OAuthGenericServiceProvider::class),

    (new Extend\Event())
        ->listen(OAuthLoginSuccessful::class, [Listeners\SyncOAuthAvatarListener::class, 'onOAuthLoginSuccessful'])
        ->listen(UserRegistered::class, [Listeners\SyncOAuthAvatarListener::class, 'onUserRegistered']),
];
