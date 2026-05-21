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
        ->roleResource()                      // Enable Role resource (default: true)
        ->permissionResource(false)           // Disable Permission resource

        // Navigation
        ->navigationGroup('System')           // Sidebar group name
        ->navigationIcon('heroicon-o-lock-closed')
        ->activeNavigationIcon('heroicon-s-lock-closed')
        ->navigationLabel('Access Control')   // Custom nav label
        ->navigationSort(5)
        ->registerNavigation(true)            // Show in navigation
        ->navigationBadge(null)               // Optional badge text
        ->navigationBadgeColor(null)          // Badge color
        ->navigationParentItem(null)          // Nest under parent item
        ->cluster(null)                       // Assign to a cluster

        // Entity exclusions
        ->excludeResources([UserResource::class])
        ->excludePages([Dashboard::class])
        ->excludeWidgets([AccountWidget::class])

        // UI layout
        ->gridColumns(3)                      // Tab grid columns
        ->checkboxColumns(5)                  // Permissions per row
        ->sectionColumnSpan(1)                // Column span for each section
        ->resourceCheckboxListColumns(2)      // Checkbox columns inside resource sections
        ->resourcesTab(true)                  // Show resources tab
        ->pagesTab(true)                      // Show pages tab
        ->widgetsTab(true)                    // Show widgets tab
        ->customPermissionsTab(true)          // Show custom permissions tab
        ->simpleResourcePermissionView(false) // Flat list instead of grouped view
        ->localizePermissionLabels(false)     // Use lang file for permission labels
        ->userRoleScopeMode('all')            // all, global_only, scoped_only
        ->roleScopeOptionsUsing(null)         // Limit selectable Authz scopes

        // Permission format
        ->permissionCase('snake')             // snake_case keys
        ->permissionSeparator(':')            // Use : separator

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
| `activeNavigationIcon()` | `string\|null` | `null` | Active state icon |
| `navigationLabel()` | `string\|null` | `null` | Custom nav label |
| `navigationSort()` | `int\|null` | config value | Sort order |
| `registerNavigation()` | `bool` | `true` | Show in sidebar |
| `navigationBadge()` | `string\|null` | `null` | Optional badge text |
| `navigationBadgeColor()` | `string\|array\|null` | `null` | Badge color |
| `navigationParentItem()` | `string\|null` | `null` | Nest under parent item |
| `cluster()` | `class-string\|null` | `null` | Assign to a cluster |
| `excludeResources()` | `array\|Closure` | `[]` | Resources to exclude from discovery |
| `excludePages()` | `array\|Closure` | `[]` | Pages to exclude from discovery |
| `excludeWidgets()` | `array\|Closure` | `[]` | Widgets to exclude from discovery |
| `gridColumns()` | `int\|array\|Closure` | `2` | Tab form grid columns |
| `checkboxColumns()` | `int\|array\|Closure` | `3` | Checkboxes per row |
| `sectionColumnSpan()` | `int\|array\|Closure` | `1` | Column span per section |
| `resourceCheckboxListColumns()` | `int\|array\|Closure` | `2` | Checkbox list columns inside resource sections |
| `resourcesTab()` | `bool\|Closure` | `true` | Show resources tab |
| `pagesTab()` | `bool\|Closure` | `true` | Show pages tab |
| `widgetsTab()` | `bool\|Closure` | `true` | Show widgets tab |
| `customPermissionsTab()` | `bool\|Closure` | `true` | Show custom permissions tab |
| `simpleResourcePermissionView()` | `bool\|Closure` | `false` | Flat list instead of grouped view |
| `localizePermissionLabels()` | `bool\|Closure` | `false` | Use lang files for permission labels |
| `userRoleScopeMode()` | `string\|Closure\|null` | `null` | Limit user role editing: `all`, `global_only`, `scoped_only` |
| `roleScopeOptionsUsing()` | `array\|Closure\|null` | `null` | Override selectable Authz scopes in RoleResource |
| `permissionCase()` | `string\|null` | `'camel'` | Key case format |
| `permissionSeparator()` | `string\|null` | `'.'` | Key separator |
| `scopeToTenant()` | `bool\|Closure` | `true` | Enable tenant scoping |
| `centralApp()` | `bool\|Closure` | `false` | Enable central app scope selector |
| `tenantRelationshipName()` | `string\|null` | `null` | Tenant relation name |
| `tenantOwnershipRelationshipName()` | `string\|null` | `null` | Tenant ownership relation name |

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
    'slug'          => 'authz/roles',
    'scope_options' => null, // null = all AuthzScope rows; pass array or Closure to restrict
    'tabs' => [
        'resources'          => true,
        'pages'              => true,
        'widgets'            => true,
        'custom_permissions' => true,
        'direct_permissions' => true, // assign individual permissions directly to a role
    ],
    'grid_columns'                => 2,
    'checkbox_columns'            => 3,
    'section_column_span'         => 1,
],
```

### User Resource UI

```php
'user_resource' => [
    'enabled'       => true,
    'auto_register' => true,   // auto-register in the panel when enabled
    'model'         => null,   // null = use app's User model; set to a FQCN to override
    'slug'          => 'authz/users',
    'navigation' => [
        'group' => 'Authz',
        'sort'  => 98,
        'icon'  => 'heroicon-o-user-group',
    ],
    'form' => [
        'fields'          => ['name', 'email', 'password'],
        'roles'           => true,
        'role_scope_mode' => 'all', // all, global_only, scoped_only
        'permissions'     => true,
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
'central_app' => false,
```

When `scoped_to_tenant` is `true` and `central_app` is `false` (the default), the user role assignment form automatically:

- **Filters role options** to only roles belonging to the current team/tenant.
- **Validates on save** that all submitted role IDs belong to the current tenant scope, throwing an `AuthorizationException` if any cross-tenant role ID is submitted.

Set `central_app => true` to disable this restriction and allow global role management across all tenants (e.g., in a super-admin panel).

Use Filament tenancy with `SyncAuthzTenant` or Authz Scopes with `Authz::withScope()` to set the active team context.

## Environment Variables

The package supports the following environment variables:

| Variable | Config Key | Default |
|----------|------------|---------|
| None | All settings in config file | Various |

Most settings are hardcoded or configured via config file since they are deployment-independent.
