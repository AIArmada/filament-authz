<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;

trait ScopesAuthzTenancy
{
    protected static function shouldScopeToTenant(): bool
    {
        if (! config('filament-authz.scoped_to_tenant', true)) {
            return false;
        }

        return (bool) app(PermissionRegistrar::class)->teams;
    }

    protected static function getTeamKey(): ?string
    {
        $registrar = app(PermissionRegistrar::class);

        return $registrar->teams ? (string) $registrar->teamsKey : null;
    }

    protected static function applyTenantScope(Builder $query): Builder
    {
        if (! static::shouldScopeToTenant()) {
            return $query;
        }

        $teamsKey = static::getTeamKey();

        if ($teamsKey === null) {
            return $query;
        }

        $teamId = getPermissionsTeamId();

        if ($teamId === null) {
            return $query->whereNull($teamsKey);
        }

        return $query->where($teamsKey, $teamId);
    }
}
