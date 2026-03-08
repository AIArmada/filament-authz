<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\UserResource\Pages;

use AIArmada\FilamentAuthz\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
