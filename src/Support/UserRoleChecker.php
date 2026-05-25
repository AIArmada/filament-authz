<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

final class UserRoleChecker
{
    public static function hasRole(mixed $user, string $role): bool
    {
        return is_object($user)
            && method_exists($user, 'hasRole')
            && (bool) $user->hasRole($role);
    }
}
