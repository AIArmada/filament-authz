<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use AIArmada\FilamentAuthz\Models\AuthzScope;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class UserAuthzForm
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        $components = [];

        if (config('filament-authz.user_resource.form.roles', true)) {
            $components[] = static::rolesComponent();
        }

        if (config('filament-authz.user_resource.form.permissions', true)) {
            $components[] = static::permissionsComponent();
        }

        if ($components === []) {
            return [];
        }

        return [
            Section::make('Access Control')
                ->description('Assign roles to manage user permissions. Direct permission assignment is available for special cases.')
                ->schema($components)
                ->columns(2),
        ];
    }

    protected static function rolesComponent(): Select
    {
        return Select::make('roles')
            ->label('Roles')
            ->options(fn (): array => static::getRoleOptions())
            ->afterStateHydrated(function (Select $component, ?Model $record): void {
                static::setRoleStateForRecord($component, $record);
            })
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Select roles to grant permissions to this user.')
            ->saveRelationshipsUsing(function (Model $record, array $state): void {
                static::syncRolesAcrossScopes($record, $state);
            });
    }

    protected static function permissionsComponent(): Select
    {
        return Select::make('permissions')
            ->label('Direct Permissions')
            ->relationship('permissions', 'name', modifyQueryUsing: fn (Builder $query): Builder => static::applyPermissionScope($query))
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Assign specific permissions directly. Use roles for standard access control.')
            ->saveRelationshipsUsing(function (Model $record, array $state): void {
                if (! method_exists($record, 'permissions')) {
                    return;
                }

                $relation = $record->permissions();
                $teamPayload = static::getTeamPivotPayload();

                if ($teamPayload !== []) {
                    $relation->syncWithPivotValues($state, $teamPayload);
                } else {
                    $relation->sync($state);
                }

                // Clear permission cache so changes take effect immediately
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
    }

    protected static function applyPermissionScope(Builder $query): Builder
    {
        $guards = (array) config('filament-authz.guards', ['web']);
        $guard = $guards[0] ?? 'web';

        $table = $query->getModel()->getTable();
        $query->where("{$table}.guard_name", $guard);

        // Permissions are typically global, so we do not scope by team_id
        // unless explicitly configured/customized to do so.
        // For standard setup, we return query as is.

        return $query;
    }

    /**
     * @return array<string, int|string|null>
     */
    protected static function getTeamPivotPayload(): array
    {
        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams || ! config('filament-authz.scoped_to_tenant', true)) {
            return [];
        }

        return [$registrar->teamsKey => getPermissionsTeamId()];
    }

    /**
     * @return array<string, string>
     */
    protected static function getRoleOptions(): array
    {
        $guards = (array) config('filament-authz.guards', ['web']);
        $guard = (string) ($guards[0] ?? 'web');

        $registrar = app(PermissionRegistrar::class);
        $teamsKey = $registrar->teamsKey;

        /** @var class-string<Model> $roleClass */
        $roleClass = $registrar->getRoleClass();

        $columns = ['id', 'name'];

        if ($registrar->teams) {
            $columns[] = $teamsKey;
        }

        /** @var Collection<int, Model> $roles */
        $roles = $roleClass::query()
            ->where('guard_name', $guard)
            ->orderBy('name')
            ->get($columns);

        $scopeLabels = static::resolveScopeLabels($roles, $teamsKey, $registrar->teams);
        $options = [];

        foreach ($roles as $role) {
            $roleId = (string) $role->getKey();
            $roleName = (string) $role->getAttribute('name');
            $scopeId = $registrar->teams ? $role->getAttribute($teamsKey) : null;
            $options[$roleId] = static::formatRoleLabel($roleName, $scopeId, $scopeLabels, $registrar->teams);
        }

        return $options;
    }

    protected static function setRoleStateForRecord(Select $component, ?Model $record): void
    {
        if ($record === null) {
            return;
        }

        $assignedRoleIds = static::getAssignedRoleIds($record);

        if ($assignedRoleIds === []) {
            $component->state([]);

            return;
        }

        $validOptions = array_keys($component->getOptions());
        $filteredRoleIds = array_values(array_intersect($assignedRoleIds, $validOptions));

        $component->state($filteredRoleIds);
    }

    protected static function syncRolesAcrossScopes(Model $record, array $state): void
    {
        if (! method_exists($record, 'syncRoles')) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);

        $selectedRoleIds = Collection::make($state)
            ->filter(fn (mixed $roleId): bool => filled($roleId))
            ->map(static fn (mixed $roleId): string => (string) $roleId)
            ->unique()
            ->values()
            ->all();

        if (! $registrar->teams || ! config('filament-authz.scoped_to_tenant', true)) {
            $record->syncRoles($selectedRoleIds);
            $registrar->forgetCachedPermissions();

            return;
        }

        $teamsKey = $registrar->teamsKey;

        /** @var class-string<Model> $roleClass */
        $roleClass = $registrar->getRoleClass();

        /** @var Collection<int, Model> $selectedRoles */
        $selectedRoles = $roleClass::query()
            ->whereIn('id', $selectedRoleIds)
            ->get(['id', $teamsKey]);

        /** @var Collection<int, Model> $existingRoles */
        $existingRoles = $roleClass::query()
            ->whereIn('id', static::getAssignedRoleIds($record))
            ->get(['id', $teamsKey]);

        $selectedByScope = static::groupRoleIdsByScope($selectedRoles, $teamsKey);
        $existingByScope = static::groupRoleIdsByScope($existingRoles, $teamsKey);
        $scopeKeys = array_values(array_unique([...array_keys($selectedByScope), ...array_keys($existingByScope)]));

        $previousScope = getPermissionsTeamId();

        try {
            foreach ($scopeKeys as $scopeKey) {
                setPermissionsTeamId($scopeKey === '__global__' ? null : $scopeKey);
                $record->syncRoles($selectedByScope[$scopeKey] ?? []);
            }
        } finally {
            setPermissionsTeamId($previousScope);
        }

        $registrar->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    protected static function getAssignedRoleIds(Model $record): array
    {
        if (! method_exists($record, 'roles')) {
            return [];
        }

        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams || ! config('filament-authz.scoped_to_tenant', true)) {
            return $record->roles()
                ->pluck('id')
                ->map(static fn (mixed $roleId): string => (string) $roleId)
                ->values()
                ->all();
        }

        $table = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelMorphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $rolePivotKey = (string) $registrar->pivotRole;

        return DB::table($table)
            ->where($modelMorphKey, $record->getKey())
            ->where('model_type', $record->getMorphClass())
            ->pluck($rolePivotKey)
            ->map(static fn (mixed $roleId): string => (string) $roleId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Model>  $roles
     * @return array<string, list<string>>
     */
    protected static function groupRoleIdsByScope(Collection $roles, string $teamsKey): array
    {
        $grouped = [];

        foreach ($roles as $role) {
            $scopeId = $role->getAttribute($teamsKey);
            $scopeKey = is_scalar($scopeId) && (string) $scopeId !== '' ? (string) $scopeId : '__global__';
            $grouped[$scopeKey] ??= [];
            $grouped[$scopeKey][] = (string) $role->getKey();
        }

        return $grouped;
    }

    /**
     * @param  Collection<int, Model>  $roles
     * @return array<string, string>
     */
    protected static function resolveScopeLabels(Collection $roles, string $teamsKey, bool $teamsEnabled): array
    {
        if (! $teamsEnabled || ! config('filament-authz.authz_scopes.enabled', false)) {
            return [];
        }

        $scopeIds = $roles
            ->pluck($teamsKey)
            ->filter(static fn (mixed $scopeId): bool => is_scalar($scopeId) && (string) $scopeId !== '')
            ->map(static fn (mixed $scopeId): string => (string) $scopeId)
            ->unique()
            ->values()
            ->all();

        if ($scopeIds === []) {
            return [];
        }

        return AuthzScope::query()
            ->whereIn('id', $scopeIds)
            ->pluck('label', 'id')
            ->mapWithKeys(static fn (mixed $label, mixed $id): array => [(string) $id => (string) $label])
            ->all();
    }

    /**
     * @param  array<string, string>  $scopeLabels
     */
    protected static function formatRoleLabel(string $name, mixed $scopeId, array $scopeLabels, bool $teamsEnabled): string
    {
        if (! $teamsEnabled || ! config('filament-authz.authz_scopes.enabled', false)) {
            return $name;
        }

        if (! is_scalar($scopeId) || (string) $scopeId === '') {
            return "{$name} (Global)";
        }

        $scopeLabel = $scopeLabels[(string) $scopeId] ?? null;

        if (! is_string($scopeLabel) || $scopeLabel === '') {
            return "{$name} (Scoped)";
        }

        return "{$name} ({$scopeLabel})";
    }
}
