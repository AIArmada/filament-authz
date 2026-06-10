---
package: filament-authz
generated: 2026-06-10
php: ^8.4
filament: ^5.6.5
spatie-permission: ^7.4.1
---

# Filament Authz Lifecycle

## 1. Overview

`aiarmada/filament-authz` is a Filament v5 authorization suite built on `spatie/laravel-permission`. It provides:

| Capability | Mechanism |
|---|---|
| Resource/Page/Widget authorization | Auto-discovered permission keys via `EntityDiscoveryService` + `PermissionKeyBuilder` |
| Panel access gating | `HasPanelAuthz` trait → `panel.{panelId}` permission |
| Page access gating | `HasPageAuthz` trait → `page.{PageClass}` permission |
| Widget visibility gating | `HasWidgetAuthz` trait → `widget.{WidgetClass}` permission |
| Super admin bypass | Configurable role name + `Gate::before` hook |
| Wildcard permissions | `orders.*` matches `orders.view`, `orders.create`, etc. |
| Authz scopes | `authz_scopes` table for named permission team contexts |
| Multi-tenancy | Spatie teams integration via `SyncAuthzTenant` middleware, `ScopesAuthzTenancy`, `AuthzScopeContext`/`AuthzScopeTeamResolver` |
| Impostoration | `ImpersonateManager` with session-based state, CSRF-safe quiet login/logout, impersonation banner |
| Artisan tooling | `authz:discover`, `authz:sync`, `authz:seeder`, `authz:super-admin`, `authz:policies` |
| Policy generation | `GeneratePoliciesCommand` produces Laravel policies from discovered permissions |

**Dependencies**: `aiarmada/commerce-support` (for `Permission`, `Role`, `AuthzScope` models and `OwnerContext`).

**Architecture**: Service `Authz` (singleton) holds `EntityDiscoveryService` and `PermissionKeyBuilder`. The `Authz` facade exposes cached entity collections and permission lookups. `FilamentAuthzPlugin` registers resources on panels and applies per-panel config overrides. `FilamentAuthzServiceProvider` wires Spatie model overrides, the custom `SessionGuard` driver, Octane listeners, Gate hooks, and team resolver.

---

## 2. Installation

### 2.1 Composer

```json
{
    "require": {
        "aiarmada/filament-authz": "self.version"
    }
}
```

The package auto-discovers via `extra.laravel.providers` → `FilamentAuthzServiceProvider`.

### 2.2 Database Migrations

Two migrations ship with the package and are auto-loaded via `loadMigrationsFrom`:

| Migration | Tables Created |
|---|---|
| `2026_01_15_000000_create_authz_scopes_table` | `authz_scopes` |
| `2026_01_15_000001_create_permission_tables` | `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` (Spatie standard) |

- `authz_scopes`: `uuid id`, `scopeable_type`/`scopeable_id` (morph), `label`, `timestampsTz`. Unique index + composite index on `(scopeable_type, scopeable_id)`.
- Spatie tables use `uuid` primary keys. Team support is conditional on `config('permission.teams')`.

### 2.3 Config Publishing

```bash
php artisan vendor:publish --tag=filament-authz-config
```

Also available: `filament-authz-translations`, `filament-authz-views`.

### 2.4 Panel Registration

```php
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;

$panel->plugin(FilamentAuthzPlugin::make());
```

This registers `RoleResource`, `PermissionResource`, and optionally `UserResource` on the panel.

### 2.5 User Model Setup

```php
use Spatie\Permission\Traits\HasRoles;
use AIArmada\FilamentAuthz\Concerns\CanBeImpersonated;
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;

class User extends Authenticatable
{
    use HasRoles;
    use CanBeImpersonated;
    use HasPanelAuthz;
}
```

### 2.6 Super Admin Role

Create via command or config:

```bash
php artisan authz:super-admin --create
```

---

## 3. Configuration

File: `config/filament-authz.php`. Key sections:

### 3.1 Database

```php
'database' => [
    'table_prefix' => 'authz_',
    'tables' => [
        'authz_scopes' => 'authz_scopes',
    ],
],
```

### 3.2 Guard & Feature Flags

| Key | Default | Purpose |
|---|---|---|
| `guards` | `['web', 'api']` | Guards to create permissions/roles for |
| `super_admin_role` | `'super_admin'` | Role name that bypasses all permission checks |
| `panel_user` | `['enabled' => false, ...]` | Panel user feature toggle |
| `wildcard_permissions` | `true` | Enable `orders.*` wildcard Gate::before hook |
| `scoped_to_tenant` | `true` | Scope roles to current Spatie team |
| `central_app` | `false` | Show team selector in central multi-tenant panel |
| `authz_scopes.enabled` | `false` | Enable authz scope feature |
| `authz_scopes.auto_create` | `true` | Auto-create AuthzScope record on scope resolution |

### 3.3 Permission Key Format

```php
'permissions' => [
    'separator' => '.',    // e.g. orders.view
    'case' => 'camel',      // snake, kebab, camel, pascal, upper_snake, lower
],
```

### 3.4 Resource Permissions

```php
'resources' => [
    'subject' => 'model',   // 'model' = class_basename of getModel(), else class_basename minus 'Resource'
    'actions' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
    'extra_actions' => [],  // per-class extra actions: ['App\Resources\OrderResource' => ['ship', 'cancel']]
    'action_labels' => [],  // custom labels for actions
    'exclude' => [],        // class-strings to exclude from discovery
],
```

### 3.5 Pages & Widgets

```php
'pages' => [
    'prefix' => 'page',
    'exclude' => [Dashboard::class],
],
'widgets' => [
    'prefix' => 'widget',
    'exclude' => [AccountWidget::class, FilamentInfoWidget::class],
],
'panels' => [
    'prefix' => 'panel',
    'exclude' => [],
],
```

### 3.6 Sync Map

```php
'sync' => [
    'permissions' => [],  // ['orders.view', 'orders.create', ...]
    'roles' => [],        // ['admin' => ['orders.*', 'users.*'], ...]
],
```

### 3.7 Navigation

```php
'navigation' => [
    'register' => true,
    'group' => 'Authz',
    'sort' => 99,
    'icons' => [
        'roles' => 'heroicon-o-shield-check',
        'roles_active' => null,
        'permissions' => 'heroicon-o-key',
    ],
],
```

### 3.8 Role Resource Tabs

```php
'role_resource' => [
    'slug' => 'authz/roles',
    'scope_options' => null,  // Closure or array limiting selectable scopes
    'tabs' => [
        'resources' => true,
        'pages' => true,
        'widgets' => true,
        'custom_permissions' => true,
        'direct_permissions' => true,
        'panels' => true,
    ],
    'grid_columns' => 2,
    'checkbox_columns' => 3,
    'section_column_span' => 1,
],
```

### 3.9 User Resource

```php
'user_resource' => [
    'enabled' => true,
    'auto_register' => true,
    'model' => null,         // null = resolve from auth config
    'slug' => 'authz/users',
    'navigation' => [...],
    'form' => [
        'fields' => ['name', 'email', 'password'],
        'roles' => true,
        'role_scope_mode' => 'all',  // all, global_only, scoped_only
        'permissions' => true,
    ],
],
```

### 3.10 Impostoration

```php
'impersonate' => [
    'enabled' => true,
    'guard' => 'web',
],
```

---

## 4. Usage

### 4.1 Entity Discovery & Permission Keys

Permission keys follow `{subject}{separator}{action}` pattern:

| Entity | Subject | Key Example |
|---|---|---|
| Resource (model mode) | Model class basename | `orders.viewAny` |
| Resource (class mode) | Resource class basename minus `Resource` | `order.viewAny` |
| Page | Page class basename | `page.settings-page` |
| Widget | Widget class basename | `widget.stats-widget` |
| Panel | Panel ID | `panel.admin` |
| Custom | Config array key | (as defined) |

### 4.2 Trait-Based Authorization

**Page** (`HasPageAuthz`):

```php
class SettingsPage extends Page
{
    use HasPageAuthz;

    public static function authzPermission(): ?string  // override for custom key
    {
        return 'custom.settings';
    }
}
```

Checks `canAccess()` → super admin bypass → `user->can(page.settingsPage)`. Also gates `shouldRegisterNavigation()`.

**Widget** (`HasWidgetAuthz`):

```php
class StatsWidget extends Widget
{
    use HasWidgetAuthz;
}
```

Checks `canView()` → super admin bypass → `user->can(widget.statsWidget)`.

**Panel** (`HasPanelAuthz`):

Checks `canAccessPanel()` → super admin bypass → `user->can(panel.{panelId})`.

### 4.3 Role Resource Form

The `PermissionTabFactory` builds a tabbed UI on the role edit/create form:

1. **Resources tab**: Grouped by package namespace. Each resource section has a `CheckboxList` with `permissions_resource_{md5}` key. Includes text search (`resource_search`) with client-side filtering via `visibleJs`.
2. **Pages tab**: Grouped by package. Checkbox list keyed by permission name.
3. **Widgets tab**: Same structure as pages.
4. **Custom Permissions tab**: From `config('filament-authz.custom_permissions')`.
5. **Panels tab**: Panel-level permissions.
6. **Direct Permissions tab**: Permissions from DB not in any discovered set, filtered by guard.

State hydration: `PermissionTabFactory::setPermissionStateForRecord()` intersects the record's permission names with component options.

### 4.4 Role CRUD Lifecycle

**Create** (`CreateRole`):
1. `mutateFormDataBeforeCreate()` → `SyncsRolePermissions::extractPermissionIds()` collects all `permissions_*` fields into `$this->permissionNames`, removes from data.
2. If teams active + scoped → injects current team ID.
3. Filament creates the model.
4. `afterCreate()` → `syncPermissionsToRole()` calls `Permission::findOrCreate()` for each name, then `$role->syncPermissions()`.

**Edit** (`EditRole`):
1. `mutateFormDataBeforeSave()` → extracts permission names.
2. Filament saves the model.
3. `afterSave()` → `syncPermissionsToRole()`.

### 4.5 User Form Authorization

`UserAuthzForm` builds role and permission selects on the user edit/create form:

- **Roles select**: Options scoped by team when `scoped_to_tenant && !central_app`. Labels include scope annotation `(Global)` / `(ScopeLabel)`.
- **Permissions select**: Filtered by guard. Syncs with pivot values including team key.
- **Save**: `syncRolesAcrossScopes()` validates cross-tenant role assignments, throws `AuthorizationException` if outside current scope.

### 4.6 Multi-Tenancy / Scoping

Two modes coexist:

**A. Spatie Teams** (default when `permission.teams = true`):
- `ScopesAuthzTenancy` trait: `shouldScopeToTenant()` checks `scoped_to_tenant` config + registrar teams flag.
- `applyTenantScope()`: adds `where(team_key, currentTeamId)` or `whereNull(team_key)`.
- `SyncAuthzTenant` middleware: syncs Spatie team ID with Filament tenant on each request, restores previous on exit (Octane-safe).

**B. Authz Scopes** (when `authz_scopes.enabled = true`):
- `AuthzScopeTeamResolver` implements `PermissionsTeamResolver` using `AuthzScopeContext`.
- `AuthzScopeContext`: scoped container singleton (`$this->app->scoped(...)`) with `set()`/`clear()`/`withScope()`.
- `AuthzScopeResolver::resolveId()`: accepts null, `AuthzScope` model, any Model (morph-resolved via `scopeable_type/scopeable_id` with `firstOrCreate` and `auto_create` option), or raw string/int ID.

### 4.7 Impersonation

**Flow**:
1. Actor clicks `ImpersonateTableAction` (table) or `ImpersonateAction` (page).
2. Modal confirms, optionally selects redirect panel path.
3. Controller `ImpersonateController` validates: actor authenticated, not already impersonating, target found, target != self, scope guard passes, actor authorized.
4. `ImpersonateManager::take()`:
   - Stores impersonator ID/guard/back-to URL in session.
   - Calls `quietLogout()` on current guard (no session regen, no events).
   - Calls `quietLogin()` on target guard.
   - Updates password hash in session to satisfy `AuthenticateSession` middleware.
   - Dispatches `TakeImpersonation` event.
5. User is now the target. Banner injected by `ImpersonationBannerMiddleware` into HTML responses.
6. **Leave:** `LeaveImpersonationController` → `manager->leave()` restores original user via `quietLogin`, dispatches `LeaveImpersonation` event.

**Session keys**:

| Key | Content |
|---|---|
| `filament_authz_impersonated_by` | Impersonator's auth ID |
| `filament_authz_impersonator_guard` | Impersonator's original guard |
| `filament_authz_impersonator_guard_using` | Guard used for impersonation |
| `filament_authz_impersonator_back_to` | Return URL |

**Scope guard**: `ImpersonationScopeGuard::canAccessTarget()` checks the target user has at least one role or direct permission assignment within current team scope.

### 4.8 User Resource Scoping

`UserResource::getEloquentQuery()` applies `ImpersonationScopeGuard::applyScopeToUserQuery()` which filters to users who have role/permission assignments in the current tenant scope via `whereExists` subqueries on `model_has_roles` and `model_has_permissions`.

### 4.9 Permission Resource

`PermissionResource` intentionally uses `withoutGlobalScopes()` — permissions are global per the Spatie model, shared across tenants. Only roles carry a `team_id`. The edit form shows an "Assignment Overview" section with role and direct-user assignment lists scoped to current team.

---

## 5. Events & Hooks

### 5.1 Gate Hooks (registered in ServiceProvider)

1. **Super Admin**: `Gate::before` checks `hasRole(super_admin_role)` globally (teams temporarily disabled). If true, all abilities pass.
2. **Wildcard**: `Gate::before` iterates user permissions, matches via `WildcardPermissionResolver`. Supports `*`, `prefix.*`, and `prefix.*.suffix` patterns.

### 5.2 Events

| Event | When | Payload |
|---|---|---|
| `TakeImpersonation` | Impersonation begins | `$impersonator`, `$impersonated` |
| `LeaveImpersonation` | Impersonation ends | `$impersonator`, `$impersonated` |
| `Login` | Real login | Clears impersonation data |
| `Logout` | Real logout | Clears impersonation data |

### 5.3 Octane Events

On `RequestReceived`, the service provider resets:
- `PermissionRegistrar::forgetCachedPermissions()`
- `Authz::clearCache()` (discovery + permission caches)

### 5.4 Blade Directives

| Directive | Function |
|---|---|
| `@impersonating` / `@endImpersonating` | `is_impersonating()` |
| `@canImpersonate` / `@endCanImpersonate` | `can_impersonate()` |
| `@canBeImpersonated($user)` / `@endCanBeImpersonated` | `can_be_impersonated()` |

### 5.5 Auth Driver Extension

Service provider extends the `session` auth driver with `AIArmada\FilamentAuthz\Guard\SessionGuard` which adds:
- `quietLogin(Authenticatable $user)`: sets session + user without Login event or session regen.
- `quietLogout()`: clears user data without Logout event, remember token update, or session regen.

### 5.6 Middleware

| Middleware | Purpose |
|---|---|
| `SyncAuthzTenant` | Sync Spatie team ID to Filament tenant (add to panel tenantMiddleware) |
| `ImpersonationBannerMiddleware` | Injects red impersonation banner into HTML `body` |

### 5.7 Model Hooks (`HasAuthzScope`)

On models using `HasAuthzScope`:
- `created`: `ensureAuthzScope()` → `firstOrCreate` on `authz_scopes`.
- `updated`: `syncAuthzScopeLabel()` → updates label if changed.
- `deleted`: cascade-deletes the associated `AuthzScope` record.

### 5.8 Extensibility

- `Authz::buildPermissionKeyUsing(Closure)`: custom key builder.
- `HasPageAuthz::authzPermission()`: override per-page permission key.
- `HasWidgetAuthz::authzPermission()`: override per-widget permission key.
- `CanBeImpersonated::canImpersonate()` / `canBeImpersonated()`: override per-model.
- `FilamentAuthzPlugin` fluent API: `scopeToTenant()`, `centralApp()`, `permissionCase()`, `userRoleScopeMode()`, `roleScopeOptionsUsing()`, etc.

### 5.9 Prohibition

`CommandProhibitor::prohibitDestructiveCommands($isProduction)` prohibits `authz:policies`, `authz:seeder`, `authz:super-admin`, and `authz:sync` in production.

---

## 6. Artisan Commands

### 6.1 `authz:discover`

```
authz:discover [--panel=] [--create] [--dry-run]
```

Discovers resources/pages/widgets/panels from a Filament panel, displays them in a table. With `--create`, persists them as `Permission` records across all configured guards.

### 6.2 `authz:sync`

```
authz:sync [--flush-cache]
```

Reads `config('filament-authz.sync.permissions')` and `config('filament-authz.sync.roles')`, creates/syncs permissions and roles across all configured guards. Destructive command (prohibitable in production).

### 6.3 `authz:seeder`

```
authz:seeder [--option=all|permissions|roles] [--panel=] [--generate] [--force]
```

Generates `database/seeders/AuthzSeeder.php` from existing roles/permissions. With `--generate`, auto-creates discovered permissions first. Destructive (prohibitable).

### 6.4 `authz:super-admin`

```
authz:super-admin [--user=] [--create]
```

Assigns the super admin role to a user. Supports interactive search by email, or `--create` to create a new user with name/email/password prompts. Destructive (prohibitable).

### 6.5 `authz:policies`

```
authz:policies [--panel=] [--resource=*] [--path=] [--force]
```

Generates Laravel `Policy` classes for discovered resource models. Each policy method delegates to `$user->can('{permission}')`. Methods that need a model parameter (`view`, `update`, `delete`, `restore`, `forceDelete`, `replicate`) receive `$user` + `$model`. Destructive (prohibitable).

---

## 7. Troubleshooting

### Config not loaded
Spatie permission migration fails with "config/permission.php not loaded". Run `php artisan config:clear`. The migration throws with `throw_if($tableNames === [])`.

### Permissions not taking effect
- `PermissionRegistrar::forgetCachedPermissions()` must be called after any permission/role mutation (done automatically in create/edit pages and Octane listener).
- In Octane, the service provider clears both Spatie cache and Authz discovery cache on each `RequestReceived`.

### CSRF token mismatch during impersonation
The custom `SessionGuard::quietLogin()` avoids session regeneration. If using standard guard, CSRF tokens rotate. Ensure the `session` driver extension is registered (automatic via service provider).

### Cross-tenant role assignment
`UserAuthzForm::syncRolesAcrossScopes()` throws `AuthorizationException` when selected roles include tenants not visible in the current scope. Ensure `scoped_to_tenant` and `central_app` are configured correctly.

### Permission discovery cache stale
Call `Authz::clearCache()` or use the facade: `\AIArmada\FilamentAuthz\Facades\Authz::clearCache()`.

### `RoleResource` not showing all roles
- `getEloquentQuery()` applies tenant scope when `!central_app`. Roles without the current team key are excluded.
- When `authz_scopes.enabled && central_app`, the query limits to configured `scope_options` if set.

### `PermissionResource` showing wrong assignment counts
Roles/users are scoped to current team in `scopedRolesQuery()` and `scopedDirectUsersQuery()` when `scoped_to_tenant && !central_app`.

### Blade directives not working
The `@impersonating`, `@canImpersonate`, and `@canBeImpersonated` directives are registered on `blade.compiler` resolution. Ensure the service provider is booted.

### Destructive commands blocked in production
`CommandProhibitor::prohibitDestructiveCommands(true)` in `AppServiceProvider::boot()` blocks seeder/sync/super-admin/policies commands. Each uses the `Prohibitable` trait's `initializeProhibitable()` check.
