<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\Authz\Support\UserRoleChecker;
use Filament\Panel;

/**
 * Add this trait to your User model for panel access control.
 *
 * Resolution order:
 * 1. Super admin role (configurable) → immediate access
 * 2. Panel permission (panel.{panelId}) → access if granted
 * 3. Fallback → denied
 *
 * Panel permissions (e.g. panel.admin, panel.affiliate) are
 * auto-discovered from registered Filament panels and surfaced
 * in the "Panels" tab of the role editor. Assigning a panel
 * permission to a role grants access to that specific panel.
 */
trait HasPanelAuthz
{
    public function canAccessPanel(Panel $panel): bool
    {
        $superAdminRole = config('authz.super_admin_role');

        if ($superAdminRole && UserRoleChecker::hasRole($this, $superAdminRole)) {
            return true;
        }

        $panelPermission = 'panel.' . $panel->getId();

        return $this->can($panelPermission);
    }
}
