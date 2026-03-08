<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\FilamentAuthz\Services\ImpersonateManager;
use Illuminate\Contracts\Auth\Authenticatable;

if (! function_exists('AIArmada\\FilamentAuthz\\is_impersonating')) {
    /**
     * Check whether the current session is impersonating a user.
     */
    function is_impersonating(?string $guard = null): bool
    {
        return app(ImpersonateManager::class)->isImpersonating();
    }
}

if (! function_exists('AIArmada\\FilamentAuthz\\can_impersonate')) {
    /**
     * Check whether the current user can impersonate other users.
     */
    function can_impersonate(?string $guard = null): bool
    {
        $guard = $guard ?? app(ImpersonateManager::class)->getCurrentAuthGuardName();

        if ($guard === null) {
            return false;
        }

        $user = app('auth')->guard($guard)->user();

        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'canImpersonate')) {
            return $user->canImpersonate();
        }

        $superAdminRole = config('filament-authz.super_admin_role');

        if ($superAdminRole && method_exists($user, 'hasRole')) {
            return $user->hasRole($superAdminRole);
        }

        return false;
    }
}

if (! function_exists('AIArmada\\FilamentAuthz\\can_be_impersonated')) {
    /**
     * Check whether the specified user can be impersonated.
     */
    function can_be_impersonated(Authenticatable $user, ?string $guard = null): bool
    {
        $guard = $guard ?? app(ImpersonateManager::class)->getCurrentAuthGuardName();

        if ($guard === null) {
            return false;
        }

        $currentUser = app('auth')->guard($guard)->user();

        if ($currentUser === null) {
            return false;
        }

        if ($currentUser->getAuthIdentifier() === $user->getAuthIdentifier()) {
            return false;
        }

        if (method_exists($user, 'canBeImpersonated') && ! $user->canBeImpersonated()) {
            return false;
        }

        return true;
    }
}

if (! function_exists('AIArmada\\FilamentAuthz\\get_impersonator')) {
    /**
     * Get the original impersonator user.
     */
    function get_impersonator(): ?Authenticatable
    {
        return app(ImpersonateManager::class)->getImpersonator();
    }
}
