<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Pages;

use AIArmada\FilamentAuthz\Concerns\SyncsRolePermissions;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    use SyncsRolePermissions;

    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->extractPermissionIds($data);

        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams || ! config('filament-authz.scoped_to_tenant', true)) {
            return $data;
        }

        $teamsKey = $registrar->teamsKey;

        if (! array_key_exists($teamsKey, $data)) {
            $teamId = getPermissionsTeamId();

            if ($teamId !== null) {
                $data[$teamsKey] = $teamId;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncPermissionsToRole();
    }
}
