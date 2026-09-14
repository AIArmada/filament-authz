<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Memoize impersonation actor-authorization verdicts for the current request.
 *
 * Table visibility checks run once per row for the same actor; without
 * memoization every row pays for a role reload. The binding is
 * container-scoped (see FilamentAuthzServiceProvider), so verdicts never
 * leak across requests under Octane.
 */
final class ImpersonationActorAuth
{
    /** @var array<string, bool> */
    private array $verdicts = [];

    /**
     * @param  callable():bool  $resolver
     */
    public function isAuthorized(Authenticatable $actor, callable $resolver): bool
    {
        $key = $actor::class . '|' . $actor->getAuthIdentifier() . '|' . $this->teamKey();

        return $this->verdicts[$key] ??= (bool) $resolver();
    }

    public function clear(): void
    {
        $this->verdicts = [];
    }

    private function teamKey(): string
    {
        $teamId = getPermissionsTeamId();

        if ($teamId === null) {
            return 'null';
        }

        if (is_scalar($teamId)) {
            return (string) $teamId;
        }

        return $teamId::class . '#' . spl_object_id($teamId);
    }
}
