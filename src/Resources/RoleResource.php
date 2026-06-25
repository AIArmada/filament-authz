<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\Authz\Concerns\ScopesAuthzTenancy;
use AIArmada\CommerceSupport\Models\Role;
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use AIArmada\FilamentAuthz\Resources\RoleResource\Concerns\HasAuthzFormComponents;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages;
use AIArmada\FilamentAuthz\Resources\RoleResource\Schemas\RoleForm;
use AIArmada\FilamentAuthz\Resources\RoleResource\Tables\RoleTable;
use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RoleResource extends Resource
{
    use HasAuthzFormComponents;
    use ScopesAuthzTenancy;

    protected static ?string $model = null;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! config('filament-authz.central_app', false)) {
            $query = static::applyTenantScope($query);
        }

        return static::applyConfiguredScopeLimit($query);
    }

    public static function getModel(): string
    {
        return config('permission.models.role', Role::class);
    }

    public static function getModelLabel(): string
    {
        return __('filament-authz::filament-authz.resource.role.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-authz::filament-authz.resource.role.plural_label');
    }

    public static function canViewAny(): bool
    {
        return static::checkAbility('role.viewAny');
    }

    public static function canCreate(): bool
    {
        return static::checkAbility('role.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::checkAbility('role.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::checkAbility('role.delete');
    }

    protected static function checkAbility(string $ability): bool
    {
        $user = Auth::user();

        if (! $user instanceof Authorizable) {
            return false;
        }

        $superAdminRole = config('authz.super_admin_role');

        if (method_exists($user, 'hasRole')) {
            $registrar = app(PermissionRegistrar::class);
            $teamsEnabled = $registrar->teams;
            $teamsKey = $registrar->teamsKey;

            if ($teamsEnabled) {
                $originalTeamId = $registrar->getPermissionsTeamId();

                try {
                    $registrar->setPermissionsTeamId(null);

                    if ($user->hasRole($superAdminRole)) {
                        return true;
                    }
                } finally {
                    $registrar->setPermissionsTeamId($originalTeamId);
                    $registrar->forgetCachedPermissions();
                }
            } else {
                if ($user->hasRole($superAdminRole)) {
                    return true;
                }
            }
        }

        return $user->can($ability);
    }

    public static function form(Schema $form): Schema
    {
        return RoleForm::configure($form, static::getModel(), static fn () => static::getAuthzFormComponents());
    }

    public static function table(Table $table): Table
    {
        return RoleTable::configure($table, static::getModel());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return static::getPlugin()?->getNavigationIcon()
            ?? config('filament-authz.navigation.icons.roles');
    }

    public static function getActiveNavigationIcon(): ?string
    {
        return static::getPlugin()?->getActiveNavigationIcon()
            ?? config('filament-authz.navigation.icons.roles_active');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPlugin()?->getNavigationLabel()
            ?? config('filament-authz.navigation.label')
            ?? __('filament-authz::filament-authz.navigation.roles');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-authz.navigation.sort');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getPlugin()?->getNavigationBadge()
            ?? config('filament-authz.navigation.badge');
    }

    /**
     * @return string | array<string> | null
     */
    public static function getNavigationBadgeColor(): string | array | null
    {
        return static::getPlugin()?->getNavigationBadgeColor()
            ?? config('filament-authz.navigation.badge_color');
    }

    public static function getNavigationParentItem(): ?string
    {
        return static::getPlugin()?->getNavigationParentItem()
            ?? config('filament-authz.navigation.parent_item');
    }

    public static function getCluster(): ?string
    {
        return static::getPlugin()?->getCluster()
            ?? config('filament-authz.navigation.cluster');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $shouldRegister = static::getPlugin()?->shouldRegisterNavigation()
            ?? config('filament-authz.navigation.register', true);

        return (bool) $shouldRegister && static::canViewAny();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return (string) config('filament-authz.role_resource.slug', 'authz/roles');
    }

    protected static function applyConfiguredScopeLimit(Builder $query): Builder
    {
        if (! config('authz.scopes.enabled', false) || ! config('filament-authz.central_app', false)) {
            return $query;
        }

        $configured = static::getConfiguredScopeOptions();

        if ($configured === null) {
            return $query;
        }

        $teamsKey = app(PermissionRegistrar::class)->teamsKey;
        $scopeIds = array_keys($configured);

        return $query->where(function (Builder $query) use ($teamsKey, $scopeIds): void {
            $query->whereNull($teamsKey);

            if ($scopeIds !== []) {
                $query->orWhereIn($teamsKey, $scopeIds);
            }
        });
    }

    /**
     * @return array<string, string> | null
     */
    protected static function getConfiguredScopeOptions(): ?array
    {
        $configured = config('filament-authz.role_resource.scope_options');

        if ($configured instanceof Closure) {
            /** @var mixed $configured */
            $configured = app()->call($configured);
        }

        if (! is_array($configured)) {
            return null;
        }

        return collect($configured)
            ->mapWithKeys(static fn (mixed $label, mixed $id): array => [(string) $id => (string) $label])
            ->all();
    }

    protected static function getPlugin(): ?FilamentAuthzPlugin
    {
        try {
            $panel = Filament::getCurrentOrDefaultPanel();

            if ($panel === null) {
                return null;
            }

            /** @var FilamentAuthzPlugin|null */
            return $panel->getPlugin(FilamentAuthzPlugin::PLUGIN_ID);
        } catch (Throwable) {
            return null;
        }
    }
}
