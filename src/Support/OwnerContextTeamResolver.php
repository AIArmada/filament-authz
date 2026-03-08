<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

final class OwnerContextTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int | string | null
    {
        return OwnerContext::resolve()?->getKey();
    }

    public function setPermissionsTeamId($id): void
    {
        if ($id instanceof Model || $id === null) {
            OwnerContext::override($id);

            return;
        }

        $teamType = config('commerce-support.owner.team_type');

        if (! is_string($teamType) || $teamType === '') {
            throw new InvalidArgumentException('commerce-support.owner.team_type must be configured to resolve a team model.');
        }

        $owner = OwnerContext::fromTypeAndId($teamType, $id);

        OwnerContext::override($owner);
    }
}
