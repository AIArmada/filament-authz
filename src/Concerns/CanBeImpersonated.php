<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use Filament\Facades\Filament;

/**
 * Add this trait to your User model for impersonation authorization.
 *
 * Features:
 * - Determines if a user can impersonate others
 * - Determines if a user can be impersonated
 * - Super admin users can impersonate by default
 * - Super admin users cannot be impersonated by default
 *
 * @example
 * ```php
 * class User extends Authenticatable
 * {
 *     use HasRoles;
 *     use CanBeImpersonated;
 *
 *     // Override if needed:
 *     public function canImpersonate(): bool
 *     {
 *         return $this->hasRole('admin');
 *     }
 * }
 * ```
 */
trait CanBeImpersonated
{
    /**
     * Determine if this user can impersonate others.
     *
     * Override this method to customize impersonation permission.
     */
    public function canImpersonate(): bool
    {
        $superAdminRole = config('filament-authz.super_admin_role');

        if ($superAdminRole && method_exists($this, 'hasRole')) {
            return $this->hasRole($superAdminRole);
        }

        return false;
    }

    /**
     * Determine if this user can be impersonated.
     *
     * By default, super admins cannot be impersonated.
     * Override this method to customize which users can be impersonated.
     */
    public function canBeImpersonated(): bool
    {
        $superAdminRole = config('filament-authz.super_admin_role');

        if ($superAdminRole && method_exists($this, 'hasRole')) {
            if ($this->hasRole($superAdminRole)) {
                return false;
            }
        }

        $currentUser = Filament::auth()->user();

        if ($currentUser !== null && $currentUser->getAuthIdentifier() === $this->getAuthIdentifier()) {
            return false;
        }

        return true;
    }
}
