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
    'guard' => 'web', // Authentication guard to use
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
        ->actions([
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
            ->record($this->record),
    ];
}
```

### LeaveImpersonationAction

Returns to the original user. Can be added to user menu:

```php
use AIArmada\FilamentAuthz\Actions\LeaveImpersonationAction;

// In Panel configuration
->userMenuItems([
    LeaveImpersonationAction::make()
        ->label('Return to Admin')
        ->icon('heroicon-o-arrow-left-on-rectangle'),
])
```

## Authorization

The `CanBeImpersonated` trait provides two methods for controlling access:

### canBeImpersonated()

Determines if this user can be impersonated by others:

```php
public function canBeImpersonated(): bool
{
    // Prevent impersonating super admins
    if ($this->hasRole('super_admin')) {
        return false;
    }

    // Prevent impersonating yourself
    if ($this->is(auth()->user())) {
        return false;
    }

    return true;
}
```

### canImpersonate()

Determines if this user can impersonate others:

```php
public function canImpersonate(): bool
{
    // Only super admins can impersonate
    return $this->hasRole('super_admin');
}
```

## Helper Functions

The package provides global helper functions:

```php
use function AIArmada\FilamentAuthz\is_impersonating;
use function AIArmada\FilamentAuthz\get_impersonator;
use function AIArmada\FilamentAuthz\can_impersonate;
use function AIArmada\FilamentAuthz\can_be_impersonated;

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
is_impersonating(?string $guard = null): bool
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
        <a href="{{ route('filament.impersonate.leave') }}">Return to admin</a>
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
use AIArmada\FilamentAuthz\Events\TakeImpersonation;

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
use AIArmada\FilamentAuthz\Events\LeaveImpersonation;

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
| `impersonate.impersonator_id` | Original user's ID |
| `impersonate.back_to` | URL to return to after leaving |
| `impersonate.guard` | Guard used for impersonation |

### Best Practices

1. **Log all impersonation events** — Use the provided events for audit trails
2. **Restrict impersonation ability** — Only allow trusted roles (e.g., super_admin)
3. **Prevent impersonating super admins** — Block impersonation of high-privilege accounts
4. **Show clear visual indicators** — The banner helps, but consider additional UI hints
5. **Review session timeout settings** — Impersonation sessions follow standard auth timeout

## Troubleshooting

### Impersonation Not Working

1. Verify `impersonate.enabled` is `true` in config
2. Check that routes are registered: `php artisan route:list | grep impersonate`
3. Ensure User model has `CanBeImpersonated` trait
4. Verify the `canImpersonate()` method returns `true` for your user

### Banner Not Showing

1. The middleware should be auto-registered when enabled
2. Check that `ImpersonationBannerMiddleware` is in the web middleware group
3. Verify you're actually in an impersonation session: `is_impersonating()`

### Cannot Return to Original User

1. Check session data: `session('impersonate.impersonator_id')`
2. Verify the `back_to` URL is set: `session('impersonate.back_to')`
3. Ensure the original user still exists in the database

### CSRF Token Errors

The custom `SessionGuard` handles this, but if you encounter issues:

1. Verify using the package's login routes (not manual auth)
2. Check that `quietLogin()` is being called, not `login()`
