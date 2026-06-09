<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that syncs the Spatie permissions team context with the current Filament tenant.
 *
 * Add this to your panel's tenant middleware when using multi-tenancy:
 *
 * ```php
 * ->tenantMiddleware([
 *     SyncAuthzTenant::class,
 * ], isPersistent: true)
 * ```
 */
class SyncAuthzTenant
{
    /**
     * Handle an incoming request.
     *
     * Under Octane, the permissions team ID and cached permissions must be
     * restored after the request to prevent cross-request contamination.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Filament::hasTenancy() && $tenant = Filament::getTenant()) {
            $previousTeamId = getPermissionsTeamId();

            setPermissionsTeamId($tenant->getKey());

            if (auth()->hasUser()) {
                auth()
                    ->user()
                    ->unsetRelation('roles')
                    ->unsetRelation('permissions');
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $response = $next($request);

            setPermissionsTeamId($previousTeamId);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $response;
        }

        return $next($request);
    }
}
