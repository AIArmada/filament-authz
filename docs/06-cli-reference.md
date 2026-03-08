---
title: CLI Reference
---

# CLI Reference

Filament Authz provides several Artisan commands to maintain your authorization system.

## authz:discover

Discover Filament entities and optionally create permissions.

```bash
php artisan authz:discover [options]
```

| Option | Description |
|--------|-------------|
| `--panel=` | The panel ID to discover entities from |
| `--create` | Create discovered permissions in database |
| `--dry-run` | Show what would be created without creating |

**Examples:**
```bash
# Preview discoveries for admin panel
php artisan authz:discover --panel=admin

# Create permissions for all discovered entities
php artisan authz:discover --panel=admin --create

# See what would be created without actually creating
php artisan authz:discover --dry-run
```

## authz:policies

Generate Laravel policies for Filament resources.

```bash
php artisan authz:policies [options]
```

| Option | Description |
|--------|-------------|
| `--panel=` | The panel ID to scan for resources |
| `--resource=*` | Specific resources to generate (class basenames) |
| `--path=` | Custom policy output path (default: `app/Policies`) |
| `--force` | Overwrite existing policy files |

**Examples:**
```bash
# Generate policies for all resources in admin panel
php artisan authz:policies --panel=admin

# Generate policy for specific resources
php artisan authz:policies --resource=User --resource=Order

# Generate to custom path
php artisan authz:policies --path=app/Authorization/Policies --force
```

## authz:super-admin

Create or assign the Super Admin role to a user.

```bash
php artisan authz:super-admin [options]
```

| Option | Description |
|--------|-------------|
| `--user=` | ID of existing user to assign the role |
| `--create` | Create a new user interactively |
| `--panel=` | Panel ID for guard configuration |

**Examples:**
```bash
# Interactive: search for existing user
php artisan authz:super-admin

# Assign to specific user by ID
php artisan authz:super-admin --user=1

# Create new user and assign super admin
php artisan authz:super-admin --create
```

## authz:sync

Sync roles and permissions from configuration.

```bash
php artisan authz:sync [options]
```

| Option | Description |
|--------|-------------|
| `--flush-cache` | Flush permission cache after sync |

Configure in `config/filament-authz.php`:
```php
'sync' => [
    'permissions' => [
        'export-reports',
        'view-analytics',
    ],
    'roles' => [
        'editor' => ['post.create', 'post.update'],
        'viewer' => ['post.viewAny', 'post.view'],
    ],
],
```

**Example:**
```bash
php artisan authz:sync --flush-cache
```

## authz:seeder

Generate a production-ready seeder from existing roles and permissions.

```bash
php artisan authz:seeder [options]
```

| Option | Description |
|--------|-------------|
| `--option=` | What to include: `all`, `permissions`, or `roles` (default: `all`) |
| `--panel=` | Panel ID to discover from |
| `--generate` | Generate permissions from entities first |
| `--force` | Overwrite existing seeder file |

**Examples:**
```bash
# Generate seeder from existing database records
php artisan authz:seeder

# Generate permissions first, then create seeder
php artisan authz:seeder --generate --panel=admin

# Only include roles in seeder
php artisan authz:seeder --option=roles --force
```

The seeder is created at `database/seeders/AuthzSeeder.php` and can be run with:
```bash
php artisan db:seed --class=AuthzSeeder
```
