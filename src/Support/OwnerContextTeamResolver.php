<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Spatie\Permission\Contracts\PermissionsTeamResolver;
use Throwable;

final class OwnerContextTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int | string | null
    {
        if (AuthzScopeContext::hasOverride()) {
            return AuthzScopeContext::resolve();
        }

        return OwnerContext::resolve()?->getKey();
    }

    /**
     * @param  int|string|Model|null  $id
     */
    public function setPermissionsTeamId($id): void
    {
        $resolvedId = $id instanceof Model ? $id->getKey() : $id;

        AuthzScopeContext::set(is_scalar($resolvedId) || $resolvedId === null ? $resolvedId : null);

        if (! $this->hasActiveHttpRequest()) {
            return;
        }

        if ($id instanceof Model || $id === null) {
            OwnerContext::setForRequest($id);

            return;
        }

        $teamType = config('commerce-support.owner.team_type');

        if (! is_string($teamType) || $teamType === '') {
            throw new InvalidArgumentException('commerce-support.owner.team_type must be configured to resolve a team model.');
        }

        $owner = OwnerContext::fromTypeAndId($teamType, $id);

        OwnerContext::setForRequest($owner);
    }

    private function hasActiveHttpRequest(): bool
    {
        try {
            $request = app('request');

            return $request instanceof Request;
        } catch (Throwable) {
            return false;
        }
    }
}
