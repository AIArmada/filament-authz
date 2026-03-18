<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared permission sync logic for Role create/edit pages.
 *
 * Uses Spatie's syncPermissions() which handles the pivot table correctly.
 *
 * @property Role $record
 * @property array<string, mixed> $data
 */
trait SyncsRolePermissions
{
    use ScopesAuthzTenancy;

    /**
     * @var list<string>
     */
    protected array $permissionNames = [];

    /**
     * Extract permission names from form data before save.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractPermissionIds(array $data): array
    {
        $permissions = [];

        // Collect all permission fields (permissions_resource_*, permissions_pages_*, permissions_widgets_*, permissions_custom)
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'permissions_') && is_array($value)) {
                $permissions = array_merge($permissions, $value);
                unset($data[$key]);
            }
        }

        $this->permissionNames = array_values(array_filter(
            array_map('strval', $permissions)
        ));

        return $data;
    }

    /**
     * Sync permissions to the role using Spatie's syncPermissions.
     *
     * Creates permissions that don't exist yet (e.g., page/widget permissions
     * that are discovered dynamically but not yet in the database).
     */
    protected function syncPermissionsToRole(): void
    {
        if ($this->permissionNames === []) {
            $this->record->syncPermissions([]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        $guardName = $this->record->guard_name;

        $permissions = collect($this->permissionNames)->map(
            fn (string $name) => Permission::findOrCreate($name, $guardName)
        );

        $this->record->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
