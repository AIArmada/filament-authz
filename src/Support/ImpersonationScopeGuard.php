<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

final class ImpersonationScopeGuard
{
    public static function canAccessTarget(Authenticatable $targetUser): bool
    {
        if (! $targetUser instanceof Model) {
            return false;
        }

        $targetKey = $targetUser->getKey();

        if ($targetKey === null) {
            return false;
        }

        return static::applyScopeToUserQuery($targetUser->newQuery()->whereKey($targetKey))->exists();
    }

    public static function applyScopeToUserQuery(Builder $query): Builder
    {
        if (! static::shouldEnforceTenantScope()) {
            return $query;
        }

        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams) {
            return $query;
        }

        $teamsKey = (string) $registrar->teamsKey;
        $teamId = getPermissionsTeamId();
        $modelMorphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $roleAssignmentsTable = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $permissionAssignmentsTable = (string) config('permission.table_names.model_has_permissions', 'model_has_permissions');

        $model = $query->getModel();
        $modelType = $model->getMorphClass();
        $modelTable = $model->getTable();
        $modelKeyName = $model->getKeyName();

        return $query->where(function (Builder $scopeQuery) use ($modelType, $modelTable, $modelKeyName, $modelMorphKey, $roleAssignmentsTable, $permissionAssignmentsTable, $teamsKey, $teamId): void {
            static::addScopedAssignmentExistsClause(
                query: $scopeQuery,
                assignmentTable: $roleAssignmentsTable,
                modelType: $modelType,
                modelTable: $modelTable,
                modelKeyName: $modelKeyName,
                modelMorphKey: $modelMorphKey,
                teamsKey: $teamsKey,
                teamId: $teamId,
            );

            static::addScopedAssignmentExistsClause(
                query: $scopeQuery,
                assignmentTable: $permissionAssignmentsTable,
                modelType: $modelType,
                modelTable: $modelTable,
                modelKeyName: $modelKeyName,
                modelMorphKey: $modelMorphKey,
                teamsKey: $teamsKey,
                teamId: $teamId,
                useOrWhereExists: true,
            );
        });
    }

    private static function shouldEnforceTenantScope(): bool
    {
        return config('filament-authz.scoped_to_tenant', true)
            && ! config('filament-authz.central_app', false);
    }

    private static function addScopedAssignmentExistsClause(
        Builder $query,
        string $assignmentTable,
        string $modelType,
        string $modelTable,
        string $modelKeyName,
        string $modelMorphKey,
        string $teamsKey,
        mixed $teamId,
        bool $useOrWhereExists = false,
    ): void {
        if ($assignmentTable === '' || $modelType === '' || $modelTable === '' || $modelKeyName === '') {
            return;
        }

        $callback = static function ($existsQuery) use ($assignmentTable, $modelType, $modelTable, $modelKeyName, $modelMorphKey, $teamsKey, $teamId): void {
            $existsQuery
                ->selectRaw('1')
                ->from($assignmentTable)
                ->where("{$assignmentTable}.model_type", $modelType)
                ->whereColumn("{$assignmentTable}.{$modelMorphKey}", "{$modelTable}.{$modelKeyName}");

            if ($teamId === null) {
                $existsQuery->whereNull("{$assignmentTable}.{$teamsKey}");
            } else {
                $existsQuery->where("{$assignmentTable}.{$teamsKey}", $teamId);
            }
        };

        if ($useOrWhereExists) {
            $query->orWhereExists($callback);
        } else {
            $query->whereExists($callback);
        }
    }
}
