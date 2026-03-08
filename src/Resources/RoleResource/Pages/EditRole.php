<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Pages;

use AIArmada\FilamentAuthz\Concerns\SyncsRolePermissions;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * @property Role $record
 */
class EditRole extends EditRecord
{
    use SyncsRolePermissions;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractPermissionIds($data);
    }

    protected function afterSave(): void
    {
        $this->syncPermissionsToRole();
    }
}
