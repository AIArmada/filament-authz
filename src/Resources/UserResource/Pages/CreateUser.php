<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\UserResource\Pages;

use AIArmada\FilamentAuthz\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
