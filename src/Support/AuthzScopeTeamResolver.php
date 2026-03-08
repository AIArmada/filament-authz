<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use Spatie\Permission\Contracts\PermissionsTeamResolver;

final class AuthzScopeTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int | string | null
    {
        return AuthzScopeContext::resolve();
    }

    public function setPermissionsTeamId($id): void
    {
        $resolvedId = AuthzScopeResolver::resolveId($id);

        AuthzScopeContext::set($resolvedId);
    }
}
