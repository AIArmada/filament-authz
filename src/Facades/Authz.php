<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection getResources(?\Filament\Panel $panel = null)
 * @method static \Illuminate\Support\Collection getPages(?\Filament\Panel $panel = null)
 * @method static \Illuminate\Support\Collection getWidgets(?\Filament\Panel $panel = null)
 * @method static \Illuminate\Support\Collection getPanels()
 * @method static array getAllPermissions(?\Filament\Panel $panel = null)
 * @method static string|null getPagePermission(string $pageClass, ?\Filament\Panel $panel = null)
 * @method static string|null getWidgetPermission(string $widgetClass, ?\Filament\Panel $panel = null)
 * @method static array getResourcePermissions(string $resourceClass, ?\Filament\Panel $panel = null)
 * @method static array getCustomPermissions()
 * @method static string buildPermissionKey(string $subject, string $action)
 */
class Authz extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AIArmada\FilamentAuthz\Authz::class;
    }
}
