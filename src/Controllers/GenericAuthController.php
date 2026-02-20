<?php

/*
 * OAuth in same window (display=page) so session is set in the same context.
 * Fixes "existing user re-login" when popup closes but session is not set (FoF OAuth popup behaviour).
 */

namespace blt950\OauthGeneric\Controllers;

use FoF\OAuth\Controllers\AuthController;

class GenericAuthController extends AuthController
{
    /**
     * Use full page redirect instead of popup so the session cookie is set in the same window.
     * Prevents "second login does nothing" when Flarum does not set session in popup for existing users.
     *
     * @return string
     */
    protected function getDisplayType(): string
    {
        return 'page';
    }
}
