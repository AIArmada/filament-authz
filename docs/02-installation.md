---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5.0+
- Spatie laravel-permission 6.0+

## Composer

Install the package via composer:

```bash
composer require aiarmada/filament-authz
```

## Configure Spatie Permission

Ensure you have run the Spatie Permission migrations:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

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
