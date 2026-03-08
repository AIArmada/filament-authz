<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Console\Concerns\Prohibitable;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class SyncAuthzCommand extends Command
{
    use Prohibitable;

    protected $signature = 'authz:sync
        {--flush-cache : Flush permission cache after sync}';

    protected $description = 'Sync roles & permissions from config(filament-authz.sync).';

    public function handle(): int
    {
        $this->initializeProhibitable();

        $config = (array) config('filament-authz.sync');
        $permissions = (array) ($config['permissions'] ?? []);
        $roles = (array) ($config['roles'] ?? []);
        $guards = $this->validateGuards((array) config('filament-authz.guards'));

        if ($guards === []) {
            $this->error('No valid guards configured.');

            return self::FAILURE;
        }

        $permissionCount = 0;
        $roleCount = 0;

        foreach ($permissions as $permission) {
            foreach ($guards as $guard) {
                Permission::findOrCreate($permission, $guard);
                $permissionCount++;
            }
        }

        foreach ($roles as $roleName => $perms) {
            foreach ($guards as $guard) {
                $role = Role::findOrCreate($roleName, $guard);
                $role->syncPermissions($perms);
                $roleCount++;
            }
        }

        if ($this->option('flush-cache')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info('Permission cache flushed.');
        }

        $this->info("Synced {$permissionCount} permissions and {$roleCount} roles.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $guards
     * @return list<string>
     */
    protected function validateGuards(array $guards): array
    {
        $configuredGuards = array_keys((array) config('auth.guards', []));
        $validGuards = [];

        foreach ($guards as $guard) {
            if (in_array($guard, $configuredGuards, true)) {
                $validGuards[] = $guard;
            } else {
                $this->warn("Guard '{$guard}' not configured in auth.guards, skipping.");
            }
        }

        return $validGuards;
    }
}
