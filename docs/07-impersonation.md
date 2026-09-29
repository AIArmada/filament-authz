---
title: Impersonation
---

# User Impersonation

Filament Authz provides a secure user impersonation feature that allows administrators to log in as another user for debugging or support purposes.

## Features

- **Secure Session Management** — Uses a custom `SessionGuard` with quiet login/logout to prevent CSRF issues
- **Panel Selection** — Modal allows choosing which panel to redirect to after impersonation
- **Visual Indicator** — Banner at the top of the page clearly shows impersonation is active
- **Origin Tracking** — Automatically returns to the original panel when leaving impersonation
- **Event Hooks** — Fire events for logging and auditing

## Setup

### 1. Enable in Config

```php
// config/filament-authz.php
'impersonate' => [
    'enabled' => true,
],
```

The authentication guard is configured in the `authz` core package:

```php
// config/authz.php
'impersonate' => [
    'guard' => env('AUTHZ_IMPERSONATE_GUARD', 'web'),
],
```

### 2. Add Trait to User Model

```php
namespace App\Models;

use AIArmada\FilamentAuthz\Concerns\CanBeImpersonated;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use CanBeImpersonated;
}
```

### 3. Add Table Action (Optional)

Add the impersonation action to your User resource table:

```php
use AIArmada\FilamentAuthz\Tables\Actions\ImpersonateTableAction;

public static function table(Table $table): Table
{
    return $table
        ->columns([...])
        ->recordActions([
            ImpersonateTableAction::make(),
        ]);
}
```

## Actions

### ImpersonateTableAction

A table action that opens a modal for selecting the redirect panel:

```php
use AIArmada\FilamentAuthz\Tables\Actions\ImpersonateTableAction;

ImpersonateTableAction::make()
    ->label('Login as User')
    ->icon('heroicon-o-user')
    ->color('warning')
    ->visible(fn ($record) => $record->id !== auth()->id());
```

Modal features:
- Dropdown to select target panel (Admin, App, etc.)
- Shows all registered Filament panels
- Remembers origin for returning later

### ImpersonateAction

A page action for record-based impersonation:

```php
use AIArmada\FilamentAuthz\Actions\ImpersonateAction;

protected function getHeaderActions(): array
{
    return [
        ImpersonateAction::make()
            ->record($this->getRecord()),
    ];
}
```

On success the action performs a full redirect back to the originating page (impersonation rotates the session id and CSRF token, so staying on the page would break subsequent requests with a 419). If impersonation cannot start, a danger notification is shown instead.

### LeaveImpersonationAction

Returns to the original user. Can be added to user menu:

```php
use AIArmada\FilamentAuthz\Actions\LeaveImpersonationAction;

// In Panel configuration
->userMenuItems([
    LeaveImpersonationAction::make()->asMenuItem(),
])
```

## Authorization

The `CanBeImpersonated` trait provides two methods for controlling access. Both
are meant to be overridden on your User model when you need different rules.

### canBeImpersonated()

The shipped default blocks global super admins and self-impersonation:

```php
public function canBeImpersonated(): bool
{
    $superAdminRole = config('authz.super_admin_role');

    if ($superAdminRole && UserRoleChecker::hasGlobalRole($this, $superAdminRole)) {
        return false;
    }

    $currentUser = Filament::auth()->user();

    if ($currentUser !== null && $currentUser->getAuthIdentifier() === $this->getAuthIdentifier()) {
        return false;
    }

    return true;
}
```

### canImpersonate()

Determines if this user can impersonate others. The shipped default requires a
**global** (unscoped) assignment of the configured super-admin role:

```php
public function canImpersonate(): bool
{
    $superAdminRole = config('authz.super_admin_role');

    if ($superAdminRole) {
        return UserRoleChecker::hasGlobalRole($this, $superAdminRole);
    }

    return false;
}
```

### Scope enforcement

When `authz.scopes.enforce` is enabled and Spatie teams are enabled, the target must have a role or direct permission assignment in the active scope. A user with no scoped assignment is intentionally not impersonatable, even by a global super-admin. This check is applied by the controller, actions, table actions, and `can_be_impersonated()` helper.

The configured super-admin role is global: a global assignment bypasses authorization gates and actor checks regardless of the active scope. It does not bypass the target scope check.

## Helper Functions

The package provides global helper functions:

```php
use function AIArmada\Authz\is_impersonating;
use function AIArmada\Authz\get_impersonator;
use function AIArmada\Authz\can_impersonate;
use function AIArmada\Authz\can_be_impersonated;

// Check if currently impersonating
if (is_impersonating()) {
    // Get the original admin user
    $admin = get_impersonator();
}

// Check whether the currently authenticated user can impersonate anyone
if (can_impersonate()) {
    // Show impersonate button
}

// Check whether a specific user can be impersonated
if (can_be_impersonated($targetUser)) {
    // Target is safe to impersonate
}
```

### Function Signatures

```php
is_impersonating(): bool
can_impersonate(?string $guard = null): bool
can_be_impersonated(Authenticatable $user, ?string $guard = null): bool
get_impersonator(): ?Authenticatable
```

`can_impersonate()` checks the currently authenticated user — it does **not** accept a target user argument. To check the target, use `can_be_impersonated($targetUser)`.

## Blade Directives

Use in Blade templates for conditional rendering:

```blade
@impersonating
    <div class="impersonation-notice">
        You are viewing as {{ auth()->user()->name }}
        <form method="POST" action="{{ route('filament-authz.impersonate.leave') }}">
            @csrf
            <button type="submit">Return to admin</button>
        </form>
    </div>
@endimpersonating

{{-- Check if the current auth user can impersonate anyone --}}
@canImpersonate
    <button wire:click="impersonate({{ $user->id }})">
        Login as {{ $user->name }}
    </button>
@endCanImpersonate

{{-- Check if a specific user can be impersonated --}}
@canBeImpersonated($user)
    <span>This user can be impersonated.</span>
@endCanBeImpersonated
```

`@canImpersonate` takes no argument — it checks the currently authenticated user. `@canBeImpersonated($user)` takes the target `Authenticatable` as its argument.

## Events

Listen to impersonation events for logging/auditing:

### TakeImpersonation

Fired when impersonation starts:

```php
use AIArmada\Authz\Events\TakeImpersonation;

class LogImpersonationStart
{
    public function handle(TakeImpersonation $event): void
    {
        Log::info('Impersonation started', [
            'impersonator_id' => $event->impersonator->id,
            'impersonated_id' => $event->impersonated->id,
        ]);
    }
}
```

### LeaveImpersonation

Fired when impersonation ends:

```php
use AIArmada\Authz\Events\LeaveImpersonation;

class LogImpersonationEnd
{
    public function handle(LeaveImpersonation $event): void
    {
        Log::info('Impersonation ended', [
            'impersonator_id' => $event->impersonator->id,
            'impersonated_id' => $event->impersonated->id,
        ]);
    }
}
```

Register in `EventServiceProvider`:

```php
protected $listen = [
    TakeImpersonation::class => [
        LogImpersonationStart::class,
    ],
    LeaveImpersonation::class => [
        LogImpersonationEnd::class,
    ],
];
```

## Impersonation Banner

When impersonating, a banner is automatically injected at the top of every page. The banner:

- Shows the impersonated user's name
- Provides a "Leave" button to return to original user
- Uses inline styles (works without Tailwind processing)

The banner is injected via `ImpersonationBannerMiddleware`, which is automatically registered when impersonation is enabled.

## Security Considerations

### Session Handling

The package uses a custom `SessionGuard` that provides:

- **quietLogin()** — Logs in without triggering session regeneration or CSRF issues
- **quietLogout()** — Logs out without destroying session data needed for returning

### Session Data

During impersonation, the following session keys are used:

| Key | Purpose |
|-----|---------|
| `authz_impersonator_id` | Original user's ID |
| `authz_impersonator_back_to` | URL to return to after leaving |
| `authz_impersonator_guard` | Guard used by the original user |
| `authz_impersonated_guard` | Guard used by the impersonated user |

### Best Practices

1. **Log all impersonation events** — Use the provided events for audit trails
2. **Restrict impersonation ability** — Only allow trusted roles (e.g., super_admin)
3. **Prevent impersonating super admins** — Block impersonation of high-privilege accounts
4. **Show clear visual indicators** — The banner helps, but consider additional UI hints
5. **Review session timeout settings** — Impersonation sessions follow standard auth timeout

## Troubleshooting

### Impersonation Not Working

1. Verify `filament-authz.impersonate.enabled` is `true` in config
2. Check that routes are registered: `php artisan route:list | grep impersonate`
3. Ensure User model has `CanBeImpersonated` trait
4. Verify the `canImpersonate()` method returns `true` for your user

### Banner Not Showing

1. The middleware should be auto-registered when enabled
2. Check that `ImpersonationBannerMiddleware` is in the web middleware group
3. Verify you're actually in an impersonation session: `is_impersonating()`

### Cannot Return to Original User

1. Check session data: `session('authz_impersonator_id')`
2. Verify the `back_to` URL is set: `session('authz_impersonator_back_to')`
3. Ensure the original user still exists in the database

### CSRF Token Errors

The custom `SessionGuard` handles this, but if you encounter issues:

1. Verify using the package's login routes (not manual auth)
2. Check that `quietLogin()` is being called, not `login()`
