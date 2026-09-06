<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\Authz\Support\UserRoleChecker;
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
 * - Uses the request-scoped discovery cache for performance
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
    public static function canView(): bool
    {
        $user = Filament::auth()?->user();

        if ($user === null) {
            return false;
        }

        $superAdminRole = config('authz.super_admin_role');

        if ($superAdminRole && UserRoleChecker::hasGlobalRole($user, $superAdminRole)) {
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
     */
    public static function getAuthzPermission(): ?string
    {
        $customPermission = static::authzPermission();

        if (is_string($customPermission) && $customPermission !== '') {
            return $customPermission;
        }

        return Authz::getWidgetPermission(static::class);
    }

    /**
     * Override to use a custom permission key.
     */
    public static function authzPermission(): ?string
    {
        return null;
    }
}
