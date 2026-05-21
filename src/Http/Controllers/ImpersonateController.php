<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Http\Controllers;

use AIArmada\FilamentAuthz\Actions\ImpersonateAction;
use AIArmada\FilamentAuthz\Services\ImpersonateManager;
use AIArmada\FilamentAuthz\Support\ImpersonationScopeGuard;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ImpersonateController
{
    public function __invoke(Request $request, string $userId): RedirectResponse
    {
        $currentUser = Filament::auth()->user();
        $guard = (string) config('filament-authz.impersonate.guard', 'web');

        if ($currentUser === null) {
            abort(403, 'Not authenticated');
        }

        if (ImpersonateAction::isImpersonating()) {
            abort(403, 'Already impersonating');
        }

        /** @var class-string<Model&Authenticatable> $userModelClass */
        $userModelClass = self::resolveUserModelClass($guard);

        /** @var Authenticatable|null $targetUser */
        $targetUser = ImpersonationScopeGuard::applyScopeToUserQuery(
            $userModelClass::query()->whereKey($userId)
        )->first();

        if ($targetUser === null) {
            abort(404, 'User not found');
        }

        if (! ImpersonationScopeGuard::canAccessTarget($targetUser)) {
            abort(404, 'User not found');
        }

        // Verify permissions
        if ($currentUser->getAuthIdentifier() === $targetUser->getKey()) {
            abort(403, 'Cannot impersonate yourself');
        }

        if (method_exists($targetUser, 'canBeImpersonated') && ! $targetUser->canBeImpersonated()) {
            abort(403, 'This user cannot be impersonated');
        }

        $isAuthorizedImpersonator = false;

        if (method_exists($currentUser, 'canImpersonate')) {
            $isAuthorizedImpersonator = (bool) $currentUser->canImpersonate();
        } else {
            $superAdminRole = (string) config('filament-authz.super_admin_role', '');

            if ($superAdminRole !== '' && method_exists($currentUser, 'hasRole')) {
                $isAuthorizedImpersonator = (bool) $currentUser->hasRole($superAdminRole);
            }
        }

        if (! $isAuthorizedImpersonator) {
            abort(403, 'Not authorized to impersonate users');
        }

        $backTo = self::sanitizeBackToUrl(request()->header('referer') ?? Filament::getUrl());
        $impersonateManager = app(ImpersonateManager::class);

        if (! $impersonateManager->take($currentUser, $targetUser, $guard, $backTo)) {
            abort(500, 'Unable to start impersonation');
        }

        $redirectTo = self::sanitizeRedirectPath($request->input('redirect_to', '/'));

        // Redirect to the selected destination (with the new session/CSRF token)
        return redirect($redirectTo)->with('status', __('filament-authz::filament-authz.impersonate.started_message', ['name' => $targetUser->name ?? $targetUser->email ?? 'User']));
    }

    /**
     * @return class-string<Model&Authenticatable>
     */
    private static function resolveUserModelClass(string $guard): string
    {
        $provider = config("auth.guards.{$guard}.provider");

        if (! is_string($provider) || $provider === '') {
            throw new RuntimeException("Auth guard [{$guard}] has no configured provider.");
        }

        $userModelClass = config("auth.providers.{$provider}.model");

        if (! is_string($userModelClass) || $userModelClass === '') {
            throw new RuntimeException("Auth provider [{$provider}] has no configured user model class.");
        }

        if (! class_exists($userModelClass) || ! is_a($userModelClass, Model::class, true) || ! is_a($userModelClass, Authenticatable::class, true)) {
            throw new RuntimeException("Auth provider [{$provider}] model [{$userModelClass}] must implement Model and Authenticatable.");
        }

        return $userModelClass;
    }

    private static function sanitizeRedirectPath(mixed $path): string
    {
        if (! is_string($path) || $path === '') {
            return '/';
        }

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:\/\//', $path) === 1) {
            return '/';
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '/';
        }

        $normalizedPath = '/' . mb_ltrim($path, '/');
        $allowedPaths = self::allowedRedirectPaths();

        return in_array($normalizedPath, $allowedPaths, true) ? $normalizedPath : '/';
    }

    /**
     * @return list<string>
     */
    private static function allowedRedirectPaths(): array
    {
        $paths = ['/'];

        foreach (Filament::getPanels() as $panel) {
            $paths[] = '/' . mb_ltrim((string) $panel->getPath(), '/');
        }

        return array_values(array_unique($paths));
    }

    /**
     * Sanitize the back-to URL to prevent open redirect via a controlled Referer header.
     *
     * Accepts relative paths and absolute same-host URLs only.
     */
    private static function sanitizeBackToUrl(string $url): string
    {
        if ($url === '') {
            return '/';
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['host'])) {
            return '/';
        }

        if (mb_strtolower($parsed['host']) !== mb_strtolower(request()->getHost())) {
            return '/';
        }

        return $url;
    }
}
