---
title: Usage
---

# Usage

## Protecting Resources

Filament Resources are protected automatically via **Laravel Policies**. The package discovers your resources and generates permission keys that match standard policy methods.

### Generated Permission Keys

For a `UserResource`, the following permissions are generated:

| Permission Key | Policy Method |
|----------------|---------------|
| `user.viewAny` | `viewAny()` |
| `user.view` | `view()` |
| `user.create` | `create()` |
| `user.update` | `update()` |
| `user.delete` | `delete()` |
| `user.restore` | `restore()` |
| `user.forceDelete` | `forceDelete()` |

### Generating Policies

Generate a policy for your models:

```bash
php artisan authz:policies --panel=admin
```

This generates a policy in `app/Policies` that checks the appropriate permissions:

```php
// Generated app/Policies/UserPolicy.php
class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('user.viewAny');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    // ... other methods
}
```

## Protecting Pages

Add the `HasPageAuthz` trait to your custom Filament Pages:

```php
namespace App\Filament\Pages;

use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use Filament\Pages\Page;

class SystemSettings extends Page
{
    use HasPageAuthz;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static string $view = 'filament.pages.system-settings';
}
```

The page will automatically require the permission `page.systemSettings` (derived from the class name).

### Custom Permission Key

Override the permission key if needed:

```php
class SystemSettings extends Page
{
    use HasPageAuthz;

    public static function authzPermission(): ?string
    {
        return 'admin.systemSettings'; // Custom key
    }
}
```

## Protecting Widgets

Add the `HasWidgetAuthz` trait to your widgets:

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentAuthz\Concerns\HasWidgetAuthz;
use Filament\Widgets\StatsOverviewWidget;

class RevenueWidget extends StatsOverviewWidget
{
    use HasWidgetAuthz;

    protected function getStats(): array
    {
        return [
            Stat::make('Revenue', '$192,000'),
        ];
    }
}
```

The widget will require the permission `widget.revenueWidget`.

## Custom Permissions

Define custom permissions beyond resources/pages/widgets in `config/filament-authz.php`:

```php
'custom_permissions' => [
    'export-reports' => 'Export Reports',
    'view-analytics' => 'View Analytics Dashboard',
    'manage-backups' => 'Manage System Backups',
    'impersonate-users', // Auto-generates label: "Impersonate Users"
],
```

These appear in the "Custom" tab of the Role resource and can be checked normally:

```php
if ($user->can('export-reports')) {
    // Allow export
}
```

## Authz Scopes

Authz Scopes let you attach roles to any model (institutions, speakers, events, etc.).

Enable Spatie teams and Authz scopes:

```php
// config/permission.php
'teams' => true,
'team_foreign_key' => 'authz_scope_id',

// config/filament-authz.php
'authz_scopes' => [
    'enabled' => true,
    'auto_create' => true,
],
```

Add scopes to your models:

```php
use AIArmada\FilamentAuthz\Concerns\HasAuthzScope;

class Workspace extends Model
{
    use HasAuthzScope;
}

class Project extends Model
{
    use HasAuthzScope;
}
```

Check permissions within a scope:

```php
use AIArmada\FilamentAuthz\Facades\Authz;

Authz::userCanInScope($user, 'project.update', $workspace);
Authz::withScope($project, fn () => $user->can('project.update'));
Authz::userHasPermissionAcrossScopes($user, 'project.update'); // global or cross-scope
```

When `central_app` is enabled, the Role resource includes a scope selector. Leave it empty for a global role.

## Programmatic Permission Building

Use the `Authz` facade to build and check permissions:

```php
use AIArmada\FilamentAuthz\Facades\Authz;

// Build a permission key using configured format
$key = Authz::buildPermissionKey('OrderItem', 'viewAny');
// Returns: 'orderItem.viewAny' (with camel case + dot separator)

// Get all permissions for a resource
$permissions = Authz::getResourcePermissions(OrderResource::class);
// Returns: ['order.viewAny' => 'View Any', 'order.view' => 'View', ...]

// Get all discovered permissions
$allPermissions = Authz::getAllPermissions();

// Check access
if (auth()->user()->can($key)) {
    // User can delete orders
}
```

### Custom Permission Key Builder

Override the default key builder for special cases:

```php
use AIArmada\FilamentAuthz\Facades\Authz;

Authz::buildPermissionKeyUsing(function (string $subject, string $action): string {
    return strtolower($subject) . ':' . strtolower($action);
});

// Now: Authz::buildPermissionKey('Order', 'Delete') returns 'order:delete'
```

## Wildcard Permissions

Grant broad access using wildcard patterns:

```php
// In a seeder or command
$role = Role::findOrCreate('order-manager', 'web');
$role->givePermissionTo('order.*');

// Now the user can:
$user->can('order.view');        // ✓ true
$user->can('order.create');      // ✓ true
$user->can('order.delete');      // ✓ true
$user->can('product.view');      // ✗ false
```

Wildcards support multiple patterns:

| Pattern | Matches |
|---------|---------|
| `*` | Everything |
| `order.*` | `order.view`, `order.create`, etc. |
| `*.view` | `order.view`, `product.view`, etc. |

## Super Admin Bypass

Users with the super admin role bypass **all** permission checks:

```php
// config/filament-authz.php
'super_admin_role' => 'super_admin',
```

Assign the role:

```bash
php artisan authz:super-admin --user=1
```

Or programmatically:

```php
$user->assignRole('super_admin');

// Now all checks pass
$user->can('anything.here'); // true
Gate::allows('any-ability'); // true
```

## User Impersonation

Impersonation allows administrators to log in as another user to debug issues or verify user experience.

### Setup

1. Add the `CanBeImpersonated` trait to your User model:

```php
namespace App\Models;

use AIArmada\FilamentAuthz\Concerns\CanBeImpersonated;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use CanBeImpersonated;
}
```

2. Enable impersonation in config:

```php
// config/filament-authz.php
'impersonate' => [
    'enabled' => true,
    'guard' => 'web',
],
```

3. Register the routes (automatically done by the service provider when enabled).

### Table Action

Add impersonation to a user table:

```php
use AIArmada\FilamentAuthz\Tables\Actions\ImpersonateTableAction;

public static function table(Table $table): Table
{
    return $table
        ->actions([
            ImpersonateTableAction::make(),
        ]);
}
```

The action shows a modal allowing selection of which panel to redirect to after impersonation.

### Page/Header Action

Add impersonation as a page action:

```php
use AIArmada\FilamentAuthz\Actions\ImpersonateAction;

protected function getHeaderActions(): array
{
    return [
        ImpersonateAction::make()
            ->record($this->record),
    ];
}
```

### Leave Impersonation

While impersonating, a banner appears at the top of the page. Users can leave by:

1. Clicking "Leave" in the banner
2. Using the `LeaveImpersonationAction` in a menu:

```php
use AIArmada\FilamentAuthz\Actions\LeaveImpersonationAction;

// In user menu
->userMenuItems([
    LeaveImpersonationAction::make(),
])
```

### Authorization

Control who can impersonate via the `CanBeImpersonated` trait methods:

```php
class User extends Authenticatable
{
    use CanBeImpersonated;

    public function canBeImpersonated(): bool
    {
        // Prevent impersonating super admins
        return !$this->hasRole('super_admin');
    }

    public function canImpersonate(): bool
    {
        // Only super admins can impersonate
        return $this->hasRole('super_admin');
    }
}
```

### Helper Functions

Check impersonation state from anywhere:

```php
use function AIArmada\FilamentAuthz\is_impersonating;
use function AIArmada\FilamentAuthz\get_impersonator;
use function AIArmada\FilamentAuthz\can_impersonate;
use function AIArmada\FilamentAuthz\can_be_impersonated;

if (is_impersonating()) {
    $originalUser = get_impersonator();
    // Show notice or restrict actions
}

if (can_impersonate($admin, $targetUser)) {
    // Allow impersonation
}
```

### Blade Directives

Use in Blade templates:

```blade
@impersonating
    <div class="alert">You are impersonating {{ auth()->user()->name }}</div>
@endimpersonating

@canImpersonate($targetUser)
    <button>Impersonate</button>
@endcanImpersonate
```

### Events

Listen to impersonation events:

```php
use AIArmada\FilamentAuthz\Events\TakeImpersonation;
use AIArmada\FilamentAuthz\Events\LeaveImpersonation;

// In EventServiceProvider
protected $listen = [
    TakeImpersonation::class => [
        LogImpersonationStart::class,
    ],
    LeaveImpersonation::class => [
        LogImpersonationEnd::class,
    ],
];
```

Event properties:

```php
// TakeImpersonation
$event->impersonator; // User who initiated impersonation
$event->impersonated; // User being impersonated

// LeaveImpersonation
$event->impersonator; // User who was impersonating
$event->impersonated; // User who was being impersonated
```

## Panel Access Control

Control which users can access which panels using the `HasPanelAuthz` trait:

```php
namespace App\Models;

use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasPanelAuthz;
}
```

The trait provides a `canAccessPanel()` method that checks for the appropriate panel permission (e.g., `panel.admin`).
