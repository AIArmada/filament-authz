<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

final class AuthzScopeTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int | string | null
    {
        return AuthzScopeContext::resolve();
    }

    public function setPermissionsTeamId(int | string | Model | null $id): void
    {
        $resolvedId = AuthzScopeResolver::resolveId($id);

        AuthzScopeContext::set($resolvedId);
    }
}
