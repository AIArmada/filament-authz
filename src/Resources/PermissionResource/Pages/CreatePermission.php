<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\PermissionResource\Pages;

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;

    protected function afterCreate(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
