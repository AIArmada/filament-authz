---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 13+
- Filament 5.0+
- Spatie laravel-permission 7.2+

## Composer

Install the package via composer:

```bash
composer require aiarmada/filament-authz
```

## Configure Spatie Permission

`filament-authz` is UUID-first.

The package ships UUID-based Spatie Permission migrations, so you do not need to publish Spatie's default migration just to get started:

```bash
php artisan migrate
```

### How UUID Support Works

This package follows Spatie Permission's documented UUID approach:

- `permissions.id` is a UUID primary key
- `roles.id` is a UUID primary key
- `model_has_permissions.permission_id` is UUID
- `model_has_roles.role_id` is UUID
- `role_has_permissions.permission_id` and `role_has_permissions.role_id` are UUID
- team / scope foreign keys stay UUID when teams are enabled

If your authenticatable model already uses UUIDs, the included schema lines up with that model out of the box.

### Important Notes

- Do **not** publish Spatie's default auto-increment migration on top of the package migration
- Keep your `model_morph_key` as a UUID column when your authenticatable model uses UUIDs
- Keep your `team_foreign_key` as a UUID column when your authz scope / tenant IDs use UUIDs

The package's UUID migration is the source of truth.

## Set Up Your User Model

Ensure your `User` model uses the `HasRoles` trait from Spatie:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### Optional: Add Impersonation Support

To enable user impersonation, add the `CanBeImpersonated` trait:

```php
use AIArmada\FilamentAuthz\Concerns\CanBeImpersonated;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    use CanBeImpersonated;
}
```

### Optional: Add Panel Access Control

To control panel access with roles:

```php
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    use HasPanelAuthz;
}
```

## Register the Plugin

Add the `FilamentAuthzPlugin` to your Filament Panel provider:

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

## Publish Configuration (Optional)

You can publish the config file if you need to customize core behaviors:

```bash
php artisan vendor:publish --tag="filament-authz-config"
```

This creates `config/filament-authz.php` with all available options.

## Initial Setup

### 1. Discover Permissions

Scan your panel and create permissions for all Resources, Pages, and Widgets:

```bash
php artisan authz:discover --panel=admin --create
```

### 2. Create Super Admin

Create the Super Admin role and assign it to a user:

```bash
php artisan authz:super-admin
```

This will prompt you to select a user or create a new one.

### 3. Generate Policies (Optional)

Generate Laravel Policies for your resources:

```bash
php artisan authz:policies --panel=admin
```

## Multi-Panel Setup

If you have multiple Filament panels, register the plugin in each:

```php
// AdminPanelProvider.php
FilamentAuthzPlugin::make()
    ->navigationGroup('Security')

// CustomerPanelProvider.php
FilamentAuthzPlugin::make()
    ->roleResource(false)  // Hide role management from customers
    ->permissionResource(false)
```

Run discovery for each panel:

```bash
php artisan authz:discover --panel=admin --create
php artisan authz:discover --panel=customer --create
```

## Verify Installation

1. Navigate to your admin panel
2. Look for "Roles" in the sidebar (under your configured navigation group)
3. Create or edit a role to see discovered permissions
