<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Facades\Authz;
use Filament\Facades\Filament;

/**
 * Add this trait to Filament Pages to enforce permission checks.
 *
 * This trait automatically checks if the current user has the required
 * permission to access the page. Super admin users bypass all checks.
 *
 * Features:
 * - Uses discovered permissions (not hardcoded names)
 * - Caches permission lookups for performance
 * - Super admin bypass built-in via Gate::before
 * - Falls back gracefully if permission not found
 *
 * @example
 * ```php
 * class SettingsPage extends Page
 * {
 *     use HasPageAuthz;
 * }
 * ```
 *
 * The page will require the permission: `page.settingsPage` (case format is configurable)
 */
trait HasPageAuthz
{
    protected static ?string $authzPermissionKey = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()?->user();

        if ($user === null) {
            return false;
        }

        $superAdminRole = config('filament-authz.super_admin_role');

        if ($superAdminRole && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return true;
        }

        $permission = static::getAuthzPermission();

        if ($permission === null) {
            return parent::canAccess();
        }

        return method_exists($user, 'can') && $user->can($permission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    /**
     * Get the permission for this page from discovered entities.
     * Caches the result for performance.
     */
    public static function getAuthzPermission(): ?string
    {
        if (static::$authzPermissionKey === null) {
            $customPermission = static::authzPermission();

            if (is_string($customPermission) && $customPermission !== '') {
                static::$authzPermissionKey = $customPermission;
            } else {
                static::$authzPermissionKey = Authz::getPagePermission(static::class) ?? '';
            }
        }

        return static::$authzPermissionKey !== '' ? static::$authzPermissionKey : null;
    }

    /**
     * Override to use a custom permission key.
     */
    public static function authzPermission(): ?string
    {
        return null;
    }
}
