---
title: Multi-Panel Support
---

# Multi-Panel Support

Filament Authz is built to support applications with multiple Filament panels effortlessly.

## Per-Panel Configuration

You can register the plugin in different panels with different settings.

### Admin Panel
```php
// AdminPanelProvider.php
$panel->plugins([
    FilamentAuthzPlugin::make()
        ->navigationGroup('Security')
        ->excludeResources([UserResource::class])
]);
```

### Customer Panel
```php
// CustomerPanelProvider.php
$panel->plugins([
    FilamentAuthzPlugin::make()
        ->roleResource(false) // Don't allow customers to edit roles
        ->permissionResource(false)
        ->scopeToTenant() // Customers see only their roles
]);
```

## Discovery Scope
When a user visits a panel, the `EntityDiscoveryService` only identifies resources, pages, and widgets registered to that specific panel. This ensures that permissions are clean and relevant to the context.

## Role Resource in Multi-Panel
The Role resource form uses the current panel to discover what should be displayed in the tabs. If you have different resources in different panels, the Role management UI will reflect that.

### Tenant Scoping
If your panel uses tenant-scoping (e.g., via `scopeToTenant()`), the Role resource will automatically apply a global scope to ensure roles are only visible to the correct tenant.

If you need a central admin panel to manage roles across multiple scopes, enable `centralApp()` with `authz.scopes.enabled`.

## SyncAuthzTenant Middleware

The `SyncAuthzTenant` middleware ensures the authorization context is properly set for each panel request when using Filament tenancy.

### Manual Registration

If you need to register the middleware manually, add it to the panel's tenant middleware:

```php
// In your PanelProvider
$panel
    ->tenantMiddleware([
        \AIArmada\FilamentAuthz\Middleware\SyncAuthzTenant::class,
    ], isPersistent: true);
```

### What It Does

1. Resolves the current tenant from the Filament panel
2. Sets the Spatie team context for authorization scoping
3. Ensures all queries in the request are properly tenant-filtered

## Panel-Specific Guards

Each panel can use a different authentication guard. The impersonation feature respects the configured guard:

```php
// Admin panel with web guard
FilamentAuthzPlugin::make()
    // Uses config('authz.impersonate.guard') or 'web'

// API panel with custom guard
FilamentAuthzPlugin::make()
    // Will still use the configured impersonate guard
```

## Panel Access Control

Panel permissions (`panel.admin`, `panel.affiliate`, etc.) are auto-discovered from all registered Filament panels. They appear in the **Panels** tab of the role editor so you can assign specific panel access to any role.

### How It Works

1. The package discovers all panels registered via `Filament::getPanels()`
2. Each panel generates a permission: `panel.{panelId}` (e.g., `panel.admin`, `panel.affiliate`)
3. These permissions appear in the "Panels" tab of the role editor
4. Add the `HasPanelAuthz` trait to your User model for automatic `canAccessPanel()` handling

```php
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;

class User extends Authenticatable implements FilamentUser
{
    use HasPanelAuthz;
}
```

The trait checks:
1. Super admin role → immediate access
2. Panel permission (`panel.{panelId}`) → access if assigned
3. Fallback → denied

### Example: Admin vs Affiliate Panel

Given two panels — `admin` and `affiliate`:

```php
// Role: "Affiliate Manager"
$role = Role::findOrCreate('affiliate-manager', 'web');
$role->givePermissionTo('panel.affiliate');  // Can only access affiliate panel

// Role: "Full Admin"
$role = Role::findOrCreate('admin', 'web');
$role->givePermissionTo(['panel.admin', 'panel.affiliate']);  // Can access both

// Super Admin bypasses all panel checks automatically
```

### Disabling Per-Panel

Hide the Panels tab or exclude specific panel IDs:

```php
FilamentAuthzPlugin::make()
    ->panelsTab(false)                    // Remove the Panels tab
    ->excludePanels(['legacy']);          // Hide specific panel from discovery
```
