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

If you need a central admin panel to manage roles across multiple scopes, enable `centralApp()` with `authz_scopes.enabled`.

## SyncAuthzTenant Middleware

The `SyncAuthzTenant` middleware ensures the authorization context is properly set for each panel request when using Filament tenancy.

### Manual Registration

If you need to register the middleware manually:

```php
// In your PanelProvider
$panel
    ->middleware([
        \AIArmada\FilamentAuthz\Middleware\SyncAuthzTenant::class,
    ]);
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
    // Uses config('filament-authz.impersonate.guard') or 'web'

// API panel with custom guard
FilamentAuthzPlugin::make()
    // Will still use the configured impersonate guard
```

## Shared Permission Keys

Permission keys are global across panels. If you have `UserResource` in both admin and customer panels, they share the same `user.*` permissions. This allows:

1. **Central role management** — Define roles once, use across panels
2. **Consistent authorization** — Same permission means same access everywhere
3. **Simplified auditing** — One set of permissions to track

To have panel-specific permissions, use different resource names or custom permissions:

```php
// Admin panel
'custom_permissions' => [
    'admin.export-users',
    'admin.impersonate',
],

// Customer panel
'custom_permissions' => [
    'customer.download-invoices',
],
```
