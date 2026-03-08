# Filament Authz

A comprehensive Filament v5 authorization package extending Spatie laravel-permission with wildcard permissions, multi-panel support, and automatic entity discovery.

## Features

- **Super Admin Bypass** — Configure a role that automatically bypasses all permission checks via `Gate::before`
- **Wildcard Permissions** — Support for patterns like `orders.*` to match `orders.view`, `orders.create`, etc.
- **Role & Permission Resources** — Clean Filament UI for managing roles and permissions with tabbed interface
- **Automatic Discovery** — Discovers Resources, Pages, and Widgets to generate permissions automatically
- **Multi-Panel Support** — Configure different authorization settings per Filament panel
- **Policy Generation** — CLI command to scaffold Laravel Policies based on discovered permissions
- **Authz Scopes + Tenant Scoping** — Scope roles to any model (institutions, speakers, etc.) with central app support and optional commerce-support integration

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5.0+
- Spatie laravel-permission 6.0+

## Installation

```bash
composer require aiarmada/filament-authz
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-authz-config
```

Publish and run Spatie Permission migrations:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

## Setup

### Add HasRoles Trait

Add the `HasRoles` trait to your User model:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### Register Plugin

Register the plugin in your Filament panel provider:

```php
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentAuthzPlugin::make(),
        ]);
}
```

## Configuration

```php
// config/filament-authz.php
return [
    // Authentication guards to support
    'guards' => ['web', 'api'],

    // Role that bypasses all permission checks
    'super_admin_role' => 'super_admin',

    // Enable wildcard permission patterns like 'orders.*'
    'wildcard_permissions' => true,

    // Scope roles and permissions to a tenant/scope (Spatie teams)
    'scoped_to_tenant' => true,

    // Allow managing roles across scopes in a central panel
    'central_app' => false,

    // Optional authz scopes (institutions, speakers, etc.)
    'authz_scopes' => [
        'enabled' => false,
        'auto_create' => true,
    ],

    // Permission key format
    'permissions' => [
        'separator' => '.',
        'case' => 'camel', // snake, kebab, camel, pascal, upper_snake, lower
    ],

    // Navigation settings
    'navigation' => [
        'group' => 'Authz',
        'sort' => 99,
    ],

    // Custom permissions beyond resources/pages/widgets
    'custom_permissions' => [
        // 'approve_posts' => 'Approve Posts',
    ],
];
```

## Usage

### Permission Macros

```php
use Filament\Actions\Action;

// Require a specific permission
Action::make('export')
    ->requiresPermission('order.export');

// Require a role
Action::make('admin-settings')
    ->requiresRole('Admin');

// Require any of multiple roles
Action::make('analytics')
    ->requiresRole(['Admin', 'Analyst']);

// Require any of multiple permissions
Action::make('reports')
    ->requiresAnyPermission(['report.view', 'report.export']);
```

### Wildcard Permissions

```php
// Grant 'orders.*' to a role
$role->givePermissionTo('orders.*');

// This now passes for any 'orders.X' check
$user->can('orders.view');   // true
$user->can('orders.create'); // true
$user->can('orders.delete'); // true
```

### Super Admin Bypass

Users with the configured super admin role automatically bypass all permission checks:

```php
// User with 'super_admin' role passes all gates
Gate::allows('any-permission'); // true
```

## Authz Scopes (Optional)

Use Authz scopes to attach roles/permissions to any model (institutions, speakers, events, etc.).

```php
// config/filament-authz.php
'authz_scopes' => [
    'enabled' => true,
    'auto_create' => true,
],

// config/permission.php
'teams' => true,
'team_foreign_key' => 'authz_scope_id',
```

```php
use AIArmada\FilamentAuthz\Concerns\HasAuthzScope;
use AIArmada\FilamentAuthz\Facades\Authz;

class Institution extends Model
{
    use HasAuthzScope;
}

Authz::userCanInScope($user, 'event.update', $institution);
```

## Commands

### Sync Permissions

Sync roles and permissions from configuration:

```bash
php artisan authz:sync
```

### Doctor

Diagnose permission configuration issues:

```bash
php artisan authz:doctor
```

### Cache

Manage permission cache:

```bash
php artisan authz:cache --flush
php artisan authz:cache --warm
```

## Permission Naming Convention

Use `{resource}.{ability}` format:

| Permission | Description |
|------------|-------------|
| `user.viewAny` | View user list |
| `user.view` | View individual user |
| `user.create` | Create users |
| `user.update` | Update users |
| `user.delete` | Delete users |

## License

MIT License. See [LICENSE](LICENSE) for details.
