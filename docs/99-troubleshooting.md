---
title: Troubleshooting
---

# Troubleshooting

## Cache Issues

If permissions are not reflecting correctly, clear the Spatie permission cache:

```bash
php artisan permission:cache-reset
```

If you're using the Authz discovery cache, clear it programmatically:

```php
use AIArmada\FilamentAuthz\Facades\Authz;

Authz::clearCache();
```

## Entity Not Showing in Role Management

If a Resource, Page, or Widget isn't appearing in the Role management UI:

1. **Check panel registration** — Ensure it's registered in the current Filament panel
2. **Check exclusions** — Review `resources.exclude`, `pages.exclude`, or `widgets.exclude` in config
3. **Check plugin exclusions** — Review `->excludeResources()`, `->excludePages()`, `->excludeWidgets()` in plugin config
4. **Verify discovery** — Run `php artisan authz:discover --panel=admin` to see what's being discovered

## Super Admin Not Bypassing Permissions

1. **Verify role name** — Check that the role name matches `config('filament-authz.super_admin_role')`:
   ```php
   // Default is 'super_admin'
   config('filament-authz.super_admin_role');
   ```

2. **Verify role assignment** — Ensure the user actually has the role:
   ```php
   $user->hasRole('super_admin'); // Should return true
   ```

3. **Check guard** — Ensure the role was created with the correct guard:
   ```php
   $user->hasRole('super_admin', 'web');
   ```

## Trait Method Conflicts

If you have custom `canAccess()` logic in Pages or `canView()` in Widgets, you have two options:

### Option 1: Call parent method
```php
public static function canAccess(): bool
{
    // Your custom logic first
    if (! static::customCheck()) {
        return false;
    }
    
    // Then delegate to trait
    return parent::canAccess();
}
```

### Option 2: Integrate permission check
```php
public static function canAccess(): bool
{
    $permission = static::getAuthzPermission();
    
    return auth()->user()?->can($permission) && static::customCheck();
}
```

## Permissions Not Being Created

If `authz:discover --create` isn't creating permissions:

1. **Check guards** — Ensure guards in config exist in `config/auth.php`:
   ```bash
   php artisan tinker
   >>> config('auth.guards')
   ```

2. **Run migrations** — Ensure Spatie Permission migrations have run:
   ```bash
   php artisan migrate:status | grep permission
   ```

## Multi-Panel Issues

When using multiple panels with different configurations:

1. **Each panel needs its own plugin instance**:
   ```php
   // AdminPanelProvider
   FilamentAuthzPlugin::make()->navigationGroup('Admin Security')
   
   // CustomerPanelProvider  
   FilamentAuthzPlugin::make()->roleResource(false)->permissionResource(false)
   ```

2. **Discovery is panel-specific** — Permissions are discovered per-panel, so run discover for each:
   ```bash
   php artisan authz:discover --panel=admin --create
   php artisan authz:discover --panel=customer --create
   ```

## Impersonation Issues

### Impersonation Not Working

1. **Verify enabled** — Check `config('filament-authz.impersonate.enabled')` is `true`
2. **Check routes** — Verify routes are registered:
   ```bash
   php artisan route:list | grep impersonate
   ```
3. **Check User model** — Ensure `CanBeImpersonated` trait is added:
   ```php
   use AIArmada\FilamentAuthz\Concerns\CanBeImpersonated;
   
   class User extends Authenticatable
   {
       use CanBeImpersonated;
   }
   ```

### Banner Not Displaying

1. **Check middleware** — The `ImpersonationBannerMiddleware` should be auto-registered
2. **Verify session** — Check impersonation is active:
   ```php
   is_impersonating(); // Should return true
   ```

### Cannot Leave Impersonation

1. **Check session data** — Required keys must exist:
   ```php
   session('impersonate.impersonator_id');
   session('impersonate.back_to');
   ```
2. **Verify original user exists** — The impersonator must still be in the database

## Octane Compatibility

The package is Laravel Octane compatible. It automatically flushes cached data between requests using the `RequestTerminated` event listener.

If you encounter stale data in Octane:

1. **Verify listener is registered** — Check the service provider is loaded
2. **Clear caches manually** in testing:
   ```php
   app(PermissionRegistrar::class)->forgetCachedPermissions();
   Authz::clearCache();
   ```

## Permission Cache Not Clearing After User Updates

When assigning roles via `UserAuthzForm`, the permission cache is automatically cleared. If using custom forms:

```php
use Spatie\Permission\PermissionRegistrar;

// After syncing roles/permissions
app(PermissionRegistrar::class)->forgetCachedPermissions();
```

## Commands Not Running in Production

The following commands are blocked in production by `CommandProhibitor`:

- `authz:discover --create` (use seeders instead)
- `authz:sync` (use seeders/migrations instead)

To run in production, use the `--force` flag if available, or configure `APP_ENV` appropriately.

## Wildcard Permissions Not Matching

1. **Verify enabled** — Check `config('filament-authz.wildcard_permissions')` is `true`
2. **Check pattern** — Wildcards use `*` character:
   - `orders.*` matches `orders.view`, `orders.create`, etc.
   - `*.view` matches `orders.view`, `products.view`, etc.
3. **Verify permission exists** — The wildcard permission must be granted:
   ```php
   $user->givePermissionTo('orders.*');
   ```

## Authz Scope Auto-Creation Failing (SQLite / Strict Databases)

If you see an integrity constraint violation when `auto_create` attempts to insert a new `authz_scopes` row:

```
SQLSTATE[23000]: Integrity constraint violation: NOT NULL constraint failed: authz_scopes.scopeable_type
```

This was a bug in `AuthzScopeResolver` where `firstOrCreate()` was called with an empty first argument, causing `scopeable_type` and `scopeable_id` to be omitted from the INSERT. This was fixed so that these columns are now passed as the matching attributes to `firstOrCreate()`.

If you encounter this on an older version, verify that `AuthzScopeResolver::resolveId()` passes `scopeable_type` and `scopeable_id` as the first argument to `firstOrCreate()`:

```php
$authzScope = AuthzScope::query()->firstOrCreate(
    [
        'scopeable_type' => $scopeableType,
        'scopeable_id' => $scopeableId,
    ],
    [
        'label' => $label,
    ],
);
```

## Tenant Scoping Not Working

1. **Verify enabled** — Check `->scopeToTenant()` on plugin or `scoped_to_tenant` in config
2. **Check teams config** — Ensure `config('permission.teams')` is `true` and `team_foreign_key` matches your scope key (e.g., `authz_scope_id`)
3. **Check resolver + context** — The resolver should match your setup and a scope must be set:
   ```php
   use Spatie\Permission\Contracts\PermissionsTeamResolver;

   app(PermissionsTeamResolver::class);
   // AuthzScopeTeamResolver when authz_scopes.enabled = true
   // OwnerContextTeamResolver when using commerce-support + teams
   ```
   For Filament tenancy, register `SyncAuthzTenant` in tenant middleware. For Authz Scopes, use `Authz::withScope()` to set scope explicitly.

## AuthorizationException When Saving User Roles

If you see `One or more selected roles are outside the current tenant scope`, a role ID was submitted that does not belong to the active team. This is a server-side security guard in `UserAuthzForm`.

- Verify the active team context is set before the form saves (check your `SyncAuthzTenant` middleware or `Authz::withScope()` call).
- If you intentionally manage roles across tenants (e.g., a super-admin panel), set `'central_app' => true` in `config/filament-authz.php` to disable the restriction.
