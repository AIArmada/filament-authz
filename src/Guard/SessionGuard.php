<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Guard;

use Illuminate\Auth\SessionGuard as BaseSessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Custom SessionGuard with quiet login/logout methods.
 *
 * These methods allow switching users without:
 * - Firing Login/Logout events
 * - Regenerating the session (which would invalidate CSRF tokens)
 * - Touching the remember_token
 */
class SessionGuard extends BaseSessionGuard
{
    /**
     * Log a user into the application without firing the Login event
     * and without regenerating the session.
     *
     * This is critical for impersonation to work with Livewire/Filament
     * because regenerating the session would invalidate the CSRF token.
     */
    public function quietLogin(Authenticatable $user): void
    {
        $this->updateSession($user->getAuthIdentifier());
        $this->setUser($user);
    }

    /**
     * Logout the user without:
     * - Updating the remember_token
     * - Firing the Logout event
     * - Regenerating the session
     */
    public function quietLogout(): void
    {
        $this->clearUserDataFromStorage();
        $this->user = null;
        $this->loggedOut = true;
    }
}
