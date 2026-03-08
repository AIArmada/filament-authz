<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Models\Role;
use Filament\Panel;

/**
 * Add this trait to your User model for panel access control.
 *
 * Features:
 * - Auto-creates panel_user role on boot
 * - Assigns role to new users automatically
 * - Works with super admin bypass
 */
trait HasPanelAuthz
{
    public static function bootHasPanelAuthz(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $config = (array) config('filament-authz.panel_user', []);

        if (! ($config['enabled'] ?? false)) {
            return;
        }

        $roleName = $config['name'] ?? 'panel_user';
        $guards = (array) config('filament-authz.guards', ['web']);

        foreach ($guards as $guard) {
            Role::findOrCreate($roleName, $guard);
        }

        static::created(function ($user) use ($roleName): void {
            if (method_exists($user, 'assignRole')) {
                $user->assignRole($roleName);
            }
        });

        static::deleting(function ($user) use ($roleName): void {
            if (method_exists($user, 'removeRole')) {
                $user->removeRole($roleName);
            }
        });
    }

    /**
     * Determine if the user can access the given panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        $superAdminRole = config('filament-authz.super_admin_role');

        if ($superAdminRole && method_exists($this, 'hasRole') && $this->hasRole($superAdminRole)) {
            return true;
        }

        $config = (array) config('filament-authz.panel_user', []);

        if (! ($config['enabled'] ?? false)) {
            return true;
        }

        $roleName = $config['name'] ?? 'panel_user';

        return method_exists($this, 'hasRole') && $this->hasRole($roleName);
    }
}
