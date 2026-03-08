<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Facades;

use AIArmada\FilamentAuthz\Authz as AuthzService;
use Closure;
use Filament\Panel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static AuthzService buildPermissionKeyUsing(Closure $callback)
 * @method static string buildPermissionKey(string $subject, string $action)
 * @method static Collection getResources(?Panel $panel = null)
 * @method static Collection getPages(?Panel $panel = null)
 * @method static Collection getWidgets(?Panel $panel = null)
 * @method static array<string, string> getCustomPermissions()
 * @method static list<string> getAllPermissions(?Panel $panel = null)
 * @method static ?string getPagePermission(string $pageClass, ?Panel $panel = null)
 * @method static ?string getWidgetPermission(string $widgetClass, ?Panel $panel = null)
 * @method static array<string, string> getResourcePermissions(string $resourceClass, ?Panel $panel = null)
 * @method static void clearCache()
 *
 * @see \AIArmada\FilamentAuthz\Authz
 */
class Authz extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuthzService::class;
    }
}
