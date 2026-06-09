<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Concerns;

use AIArmada\FilamentAuthz\Forms\Components\PermissionTabFactory;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Model;

trait HasAuthzFormComponents
{
    public static function getAuthzFormComponents(): Tabs
    {
        return PermissionTabFactory::getAuthzFormComponents();
    }

    public static function setPermissionStateForRecord(CheckboxList $component, Model $record): void
    {
        PermissionTabFactory::setPermissionStateForRecord($component, $record);
    }
}
