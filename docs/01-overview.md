---
title: Overview
---

# Filament Authz

Filament Authz is a comprehensive authorization package for Filament v5, built on top of `spatie/laravel-permission`. It provides an automated, developer-friendly way to manage roles and permissions across multiple panels and multi-tenant environments.

## Features

- **Automatic Discovery** — Automatically discovers Resources, Pages, and Widgets to generate permissions
- **Multi-Panel Support** — Configure different authorization settings for each Filament panel
- **Authz Scopes** — Optional model-backed scopes for institutions, speakers, and more
- **Tenant Scoping** — Seamless support for multi-tenant applications with scoped roles and permissions
- **Central App Mode** — Manage roles across scopes from a single panel
- **Enhanced UI** — Beautiful Role resource with tabbed interface, master toggles, and collapsible sections
- **Wildcard Permissions** — Support for flexible wildcard matching (e.g., `user.*`, `*.view`)
- **Policy Generation** — CLI command to scaffold Laravel Policies based on discovered permissions
- **Super Admin Bypass** — Built-in bypass logic for a designated Super Admin role
- **User Impersonation** — Securely impersonate users with banner notification and panel selection
- **Fluent Plugin API** — Clean, closure-based API for per-panel configuration
- **UUID Support** — Built-in UUID primary keys for Role and Permission models
- **Laravel Octane Compatible** — Automatic cache clearing between Octane requests

## Core Concepts

### Discovery vs. Generation

Unlike packages that rely on generated permission files, Filament Authz **dynamically discovers** your Filament entities. As you add new Resources, Pages, or Widgets, they automatically appear in the Role management UI without running commands.

```php
// Permissions are discovered automatically from:
// - Resources: UserResource → user.viewAny, user.create, etc.
// - Pages: SettingsPage → page.settingsPage
// - Widgets: StatsWidget → widget.statsWidget
```

### Permission Keys

Permission keys are constructed using a configurable format:

| Setting | Default | Example |
|---------|---------|---------|
| Case | `camel` | `user`, `orderItem` |
| Separator | `.` | `user.create`, `order.view` |

Configure in `config/filament-authz.php`:
```php
'permissions' => [
    'separator' => '.',
    'case' => 'camel', // snake, kebab, camel, pascal, upper_snake, lower
],
```

### Authz Scopes

Authz Scopes let you attach roles and permissions to any model (institutions, speakers, events, etc.) instead of a single tenant type. Scopes are stored in the `authz_scopes` table and can be resolved from models or IDs.

Enable in config:

```php
'authz_scopes' => [
    'enabled' => true,
    'auto_create' => true,
],
```

Use `HasAuthzScope` on scopeable models to create scopes automatically and set readable labels.

### Multi-Tenancy

When `scopeToTenant()` is enabled on the plugin (default), roles and permissions are automatically filtered by the current tenant context. You can drive the context using Filament tenancy + `SyncAuthzTenant`, or with Authz Scopes.

```php
FilamentAuthzPlugin::make()
    ->scopeToTenant()
    ->centralApp()
    ->tenantRelationshipName('organization')
    ->tenantOwnershipRelationshipName('owner');
```

## Quick Start

```php
// 1. Register in your Panel
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentAuthzPlugin::make(),
        ]);
}

// 2. Discover and create permissions
// php artisan authz:discover --panel=admin --create

// 3. Create a super admin
// php artisan authz:super-admin

// 4. Protect a page
use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;

class SettingsPage extends Page
{
    use HasPageAuthz;
}
```

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5.0+
- Spatie laravel-permission 6.0+

## Architecture

### Services

| Service | Purpose |
|---------|---------|
| `Authz` | Main service for entity discovery, permission building, and caching |
| `EntityDiscoveryService` | Discovers Filament Resources, Pages, and Widgets |
| `PermissionKeyBuilder` | Builds permission keys with configurable case and separator |
| `WildcardPermissionResolver` | Resolves wildcard patterns like `orders.*` |
| `ImpersonateManager` | Manages user impersonation session state |

### Models

| Model | Purpose |
|-------|---------|
| `Role` | Extends Spatie Role with UUID support and tenant scoping |
| `Permission` | Extends Spatie Permission with UUID support |
| `AuthzScope` | Model-backed scope for tenant or domain-specific roles |

### Traits

| Trait | Purpose |
|-------|---------|
| `HasPageAuthz` | Protects Filament Pages with permission checks |
| `HasWidgetAuthz` | Protects Filament Widgets with permission checks |
| `HasPanelAuthz` | Adds panel access control with auto-role assignment |
| `HasAuthzScope` | Creates and maintains Authz Scopes for models |
| `CanBeImpersonated` | Adds impersonation capability to User models |
| `SyncsRolePermissions` | Shared permission sync logic for Role pages |
| `ScopesAuthzTenancy` | Applies tenant scoping to queries |
