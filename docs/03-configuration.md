---
title: Configuration
---

# Configuration

Filament Authz can be configured globally via the config file or per-panel via the fluent Plugin API.

## Fluent Plugin API

The recommended way to configure the package is within your Panel provider. Plugin settings override config file defaults.

```php
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;

$panel->plugins([
    FilamentAuthzPlugin::make()
        // Resource registration
        ->roleResource()                    // Enable Role resource (default: true)
        ->permissionResource(false)         // Disable Permission resource
        
        // Navigation
        ->navigationGroup('System')         // Sidebar group name
        ->navigationIcon('heroicon-o-lock-closed')
        ->navigationSort(5)
        ->registerNavigation(true)          // Show in navigation
        
        // Entity exclusions
        ->excludeResources([UserResource::class])
        ->excludePages([Dashboard::class])
        ->excludeWidgets([AccountWidget::class])
        
        // UI layout
        ->gridColumns(3)                    // Tab grid columns
        ->checkboxColumns(5)                // Permissions per row
        ->resourcesTab(true)                // Show resources tab
        ->pagesTab(true)                    // Show pages tab
        ->widgetsTab(true)                  // Show widgets tab
        ->customPermissionsTab(true)        // Show custom tab
        ->userRoleScopeMode('all')          // all, global_only, scoped_only
        ->roleScopeOptionsUsing(null)       // Limit selectable Authz scopes

        // Permission format
        ->permissionCase('snake')           // snake_case keys
        ->permissionSeparator(':')          // Use : separator
        
        // Multitenancy / scopes
        ->scopeToTenant()
        ->centralApp()
        ->tenantRelationshipName('organization')
        ->tenantOwnershipRelationshipName('owner')
]);
```

### Plugin API Reference

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `roleResource()` | `bool\|Closure` | `true` | Register RoleResource |
| `permissionResource()` | `bool\|Closure` | `true` | Register PermissionResource |
| `navigationGroup()` | `string\|null` | config value | Sidebar group |
| `navigationIcon()` | `string\|null` | config value | Icon override |
| `navigationSort()` | `int\|null` | config value | Sort order |
| `registerNavigation()` | `bool` | `true` | Show in sidebar |
| `excludeResources()` | `array` | `[]` | Resources to exclude |
| `excludePages()` | `array` | `[]` | Pages to exclude |
| `excludeWidgets()` | `array` | `[]` | Widgets to exclude |
| `gridColumns()` | `int` | `2` | Form grid columns |
| `checkboxColumns()` | `int` | `3` | Checkboxes per row |
| `userRoleScopeMode()` | `string` | `'all'` | Limit user role editing to all/global/scoped roles |
| `roleScopeOptionsUsing()` | `array\|Closure\|null` | `null` | Override the selectable Authz scopes in RoleResource |
| `permissionCase()` | `string` | `'camel'` | Key case format |
| `permissionSeparator()` | `string` | `'.'` | Key separator |
| `scopeToTenant()` | `bool` | `true` | Enable tenant scoping |
| `centralApp()` | `bool` | `false` | Enable central app scope selector |
| `tenantRelationshipName()` | `string` | `null` | Tenant relation name |
| `tenantOwnershipRelationshipName()` | `string` | `null` | Tenant ownership relation name |

## Config File Reference

The `config/filament-authz.php` file contains default settings. Publish with:

```bash
php artisan vendor:publish --tag=filament-authz-config
```

### Guards

Authentication guards the package supports. Permissions are created for each guard.

```php
'guards' => ['web', 'api'],
```

### Super Admin Role

Role name that bypasses **all** permission checks via `Gate::before`.

```php
'super_admin_role' => 'super_admin',
```

### Panel User Role

Optional role automatically assigned to new users for basic panel access.

```php
'panel_user' => [
    'enabled' => false,
    'name' => 'panel_user',
],
```

### Wildcard Permissions

Enable pattern matching like `orders.*` to match `orders.view`, `orders.create`, etc.

```php
'wildcard_permissions' => true,
```

### Tenant Scoping & Authz Scopes

```php
'scoped_to_tenant' => true,
'central_app' => false,

'authz_scopes' => [
    'enabled' => false,
    'auto_create' => true,
],
```

When using Authz Scopes, enable Spatie teams in `config/permission.php` and set the team key:

```php
'teams' => true,
'team_foreign_key' => 'authz_scope_id',
```

When `authz_scopes.enabled` is true, the package uses Authz scopes for team resolution instead of commerce-support's OwnerContext.

### Permission Key Format

How permission keys are constructed.

```php
'permissions' => [
    'separator' => '.',      // Separator between subject and action
    'case' => 'camel',       // snake, kebab, camel, pascal, upper_snake, lower
],
```

**Examples by case:**

| Case | Input | Output |
|------|-------|--------|
| `kebab` | `OrderItem.viewAny` | `order-item.view-any` |
| `snake` | `OrderItem.viewAny` | `order_item.view_any` |
| `camel` | `OrderItem.viewAny` | `orderItem.viewAny` |

### Resource Discovery

Configure how resources are discovered and what actions generate permissions.

```php
'resources' => [
    'subject' => 'model',    // Use model name (not resource name)
    'actions' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
    'exclude' => [],         // Classes to exclude
],
```

### Page Discovery

```php
'pages' => [
    'prefix' => 'page',      // Permission prefix
    'exclude' => [
        \Filament\Pages\Dashboard::class,
    ],
],
```

### Widget Discovery

```php
'widgets' => [
    'prefix' => 'widget',
    'exclude' => [
        \Filament\Widgets\AccountWidget::class,
        \Filament\Widgets\FilamentInfoWidget::class,
    ],
],
```

### Custom Permissions

Additional permissions beyond discovered entities.

```php
'custom_permissions' => [
    'export-reports' => 'Export Reports',     // key => label
    'view-analytics',                          // auto-generates label
],
```

### Navigation

```php
'navigation' => [
    'group' => 'Authz',
    'sort' => 99,
    'icons' => [
        'roles' => 'heroicon-o-shield-check',
        'permissions' => 'heroicon-o-key',
    ],
],
```

### Role Resource UI

```php
'role_resource' => [
    'slug' => 'authz/roles',
    'scope_options' => null, // Null => all AuthzScope rows
    'tabs' => [
        'resources' => true,
        'pages' => true,
        'widgets' => true,
        'custom_permissions' => true,
    ],
    'grid_columns' => 2,
    'checkbox_columns' => 3,
],
```

### User Resource UI

```php
'user_resource' => [
    'form' => [
        'fields' => ['name', 'email', 'password'],
        'roles' => true,
        'role_scope_mode' => 'all', // all, global_only, scoped_only
        'permissions' => true,
    ],
],
```

### Sync Configuration

Define roles and permissions to sync from config.

```php
'sync' => [
    'permissions' => [
        'export-reports',
        'view-analytics',
    ],
    'roles' => [
        'editor' => ['post.create', 'post.update', 'post.delete'],
        'viewer' => ['post.viewAny', 'post.view'],
    ],
],
```

Run sync with: `php artisan authz:sync --flush-cache`

### Impersonation

Configure user impersonation behavior.

```php
'impersonate' => [
    'enabled' => true,      // Enable impersonation feature
    'guard' => 'web',       // Authentication guard for impersonation
],
```

When impersonation is enabled:
- A modal allows selecting which panel to redirect to after impersonating
- A banner shows at the top of the page while impersonating
- Leaving impersonation returns you to the original panel

### Tenant Scoping

Enable multi-tenant support for roles and permissions.

```php
'scoped_to_tenant' => true,
```

When enabled, roles are filtered by the current tenant/scope context. Use Filament tenancy with `SyncAuthzTenant` or Authz Scopes with `Authz::withScope()`.

## Environment Variables

The package supports the following environment variables:

| Variable | Config Key | Default |
|----------|------------|---------|
| None | All settings in config file | Various |

Most settings are hardcoded or configured via config file since they are deployment-independent.
