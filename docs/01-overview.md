---
title: Overview
---

# Filament Authz

Filament Authz is the Filament-facing authorization package for Commerce applications. Built on top of `spatie/laravel-permission`, it adds discovery, resources, panel configuration, tenant-aware team resolution, and impersonation tooling for Filament v5.

## Purpose

Use this package when you need authorization management inside Filament panels, especially when the panel should discover permissions from resources, pages, and widgets automatically.

## What this package owns

- The `FilamentAuthzPlugin` fluent API and per-panel overrides
- Filament resources for roles, permissions, and the optional user resource
- Permission discovery for Filament resources, pages, and widgets
- Permission key formatting, wildcard resolution, and sync helpers
- Authz scope resolution for model-backed permission teams
- Impersonation routes, middleware, banner UI, and manager services
- Console commands such as `authz:discover`, `authz:policies`, `authz:super-admin`, and `authz:sync`

## What this package does not own

- Your application's `User` model, authenticatable tables, or user-specific business rules
- Domain authorization policy logic outside the permission checks it scaffolds or supports
- Non-Filament authorization UI for your application
- Tenant ownership semantics themselves when your app uses `commerce-support` owner context

## Related packages

- `spatie/laravel-permission` provides the underlying roles, permissions, and team mechanics
- `filament/filament` provides the panel, page, widget, and resource surfaces this package discovers
- `aiarmada/commerce-support` is used when permission teams should follow `OwnerContext` instead of Authz scopes

## Main models services or surfaces

- `FilamentAuthzPlugin` configures panel behavior and resource registration
- `RoleResource`, `PermissionResource`, and `UserResource` provide the admin UI
- `Authz` coordinates discovery, permission building, and cache management
- `EntityDiscoveryService` finds Filament resources, pages, and widgets
- `PermissionKeyBuilder` and `WildcardPermissionResolver` generate and match permission keys
- `AuthzScope` models scope-backed teams for roles and permissions
- `ImpersonateManager` manages impersonation state, routes, and cleanup

## Owner scoping and security notes

- Tenant scoping relies on Spatie teams. When `authz.scopes.enabled` is true, `AuthzScopeTeamResolver` becomes the team resolver.
- When Authz scopes are disabled but `commerce-support` owner context and Spatie teams are both enabled, the package falls back to `OwnerContextTeamResolver`.
- `central_app` widens management scope intentionally; when it is `false`, user-role assignment remains constrained to the current team context.
- The user role form revalidates submitted role IDs on save and throws an `AuthorizationException` for cross-scope submissions.
- Impersonation routes are registered under `web` + `auth` middleware and the banner middleware is appended to the `web` group when impersonation is enabled.

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
- **UUID-First Permission Schema** — Ships UUID-based permission-table migrations plus the `authz_scopes` migration
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

Configure in `config/authz.php`:
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
'scopes' => [
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

## Database and migration ownership

`filament-authz` ships UUID-first migrations for:

- `permissions`
- `roles`
- `model_has_permissions`
- `model_has_roles`
- `role_has_permissions`
- `authz_scopes`

That means:

- do **not** run Spatie's default auto-increment permission migration on top of the package migration
- keep your application's `User` model and user table under application ownership
- align Spatie teams configuration with your active scope key when using tenant or Authz-scope-aware permissions

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
- Laravel 13+
- Filament 5.0+
- Spatie laravel-permission 7.2+

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

| Model | Purpose | Location |
|-------|---------|--------|
| `Role` | Extends Spatie Role with tenant scoping helpers | `commerce-support` |
| `Permission` | Extends Spatie Permission | `commerce-support` |
| `AuthzScope` | Model-backed scope for tenant or domain-specific roles | `commerce-support` |

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

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Multi-Panel](05-multi-panel.md)
- [CLI Reference](06-cli-reference.md)
- [Impersonation](07-impersonation.md)
- [Troubleshooting](99-troubleshooting.md)
