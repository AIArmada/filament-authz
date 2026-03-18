<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Http\Controllers;

use AIArmada\FilamentAuthz\Actions\ImpersonateAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ImpersonateController
{
    public function __invoke(Request $request, string $userId): RedirectResponse
    {
        $currentUser = Filament::auth()->user();
        $guard = config('filament-authz.impersonate.guard', 'web');

        if ($currentUser === null) {
            abort(403, 'Not authenticated');
        }

        if (ImpersonateAction::isImpersonating()) {
            abort(403, 'Already impersonating');
        }

        // Get user model class from auth config
        /** @var class-string<Model&Authenticatable> $userModelClass */
        $userModelClass = config('auth.providers.users.model');

        /** @var Authenticatable|null $targetUser */
        $targetUser = $userModelClass::find($userId);

        if ($targetUser === null) {
            abort(404, 'User not found');
        }

        // Verify permissions
        if ($currentUser->getAuthIdentifier() === $targetUser->getKey()) {
            abort(403, 'Cannot impersonate yourself');
        }

        if (method_exists($currentUser, 'canImpersonate') && ! $currentUser->canImpersonate()) {
            abort(403, 'You cannot impersonate users');
        }

        if (method_exists($targetUser, 'canBeImpersonated') && ! $targetUser->canBeImpersonated()) {
            abort(403, 'This user cannot be impersonated');
        }

        $superAdminRole = config('filament-authz.super_admin_role');
        if ($superAdminRole && method_exists($currentUser, 'hasRole') && ! $currentUser->hasRole($superAdminRole)) {
            abort(403, 'Only super admins can impersonate');
        }

        // Store impersonation session data
        Session::put(ImpersonateAction::SESSION_KEY, $currentUser->getAuthIdentifier());
        Session::put(ImpersonateAction::SESSION_GUARD_KEY, $guard);
        Session::put(ImpersonateAction::SESSION_BACK_TO_KEY, request()->header('referer') ?? Filament::getUrl());

        // Log in as the target user (this will regenerate the session)
        Auth::guard($guard)->login($targetUser);

        // Get redirect destination from form input
        $redirectTo = $request->input('redirect_to', '/');

        // Redirect to the selected destination (with the new session/CSRF token)
        return redirect($redirectTo)->with('status', __('filament-authz::filament-authz.impersonate.started_message', ['name' => $targetUser->name ?? $targetUser->email ?? 'User']));
    }
}
