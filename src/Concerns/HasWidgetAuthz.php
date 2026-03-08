<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Facades\Authz;
use Filament\Facades\Filament;

/**
 * Add this trait to Filament Widgets to enforce permission checks.
 *
 * This trait automatically checks if the current user has the required
 * permission to view the widget. Super admin users bypass all checks.
 *
 * Features:
 * - Uses discovered permissions (not hardcoded names)
 * - Caches permission lookups for performance
 * - Super admin bypass built-in via Gate::before
 * - Falls back gracefully if permission not found
 *
 * @example
 * ```php
 * class StatsWidget extends Widget
 * {
 *     use HasWidgetAuthz;
 * }
 * ```
 *
 * The widget will require the permission: `widget.statsWidget` (case format is configurable)
 *
 * @requires The parent class must implement `canView(): bool`
 */
trait HasWidgetAuthz
{
    protected static ?string $authzPermissionKey = null;

    public static function canView(): bool
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
            return parent::canView();
        }

        return method_exists($user, 'can') && $user->can($permission);
    }

    /**
     * Get the permission for this widget from discovered entities.
     * Caches the result for performance.
     */
    public static function getAuthzPermission(): ?string
    {
        if (static::$authzPermissionKey === null) {
            $customPermission = static::authzPermission();

            if (is_string($customPermission) && $customPermission !== '') {
                static::$authzPermissionKey = $customPermission;
            } else {
                static::$authzPermissionKey = Authz::getWidgetPermission(static::class) ?? '';
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
