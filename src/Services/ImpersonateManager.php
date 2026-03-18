<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Events\LeaveImpersonation;
use AIArmada\FilamentAuthz\Events\TakeImpersonation;
use Exception;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use InvalidArgumentException;

/**
 * ImpersonateManager service.
 *
 * Manages user impersonation using session-based state tracking.
 * Uses custom SessionGuard methods to avoid CSRF token regeneration.
 */
class ImpersonateManager
{
    public const SESSION_KEY = 'filament_authz_impersonated_by';

    public const SESSION_GUARD = 'filament_authz_impersonator_guard';

    public const SESSION_GUARD_USING = 'filament_authz_impersonator_guard_using';

    public const SESSION_BACK_TO = 'filament_authz_impersonator_back_to';

    public function __construct(
        private readonly Application $app
    ) {}

    /**
     * Check if currently impersonating a user.
     */
    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /**
     * Get the original impersonator's ID.
     */
    public function getImpersonatorId(): mixed
    {
        return session(self::SESSION_KEY);
    }

    /**
     * Get the original impersonator's guard name.
     */
    public function getImpersonatorGuardName(): ?string
    {
        return session(self::SESSION_GUARD);
    }

    /**
     * Get the guard name being used for impersonation.
     */
    public function getImpersonatorGuardUsingName(): ?string
    {
        return session(self::SESSION_GUARD_USING);
    }

    /**
     * Get the URL to redirect back to after leaving impersonation.
     */
    public function getBackTo(): ?string
    {
        return session(self::SESSION_BACK_TO);
    }

    /**
     * Alias for getBackTo().
     */
    public function getBackToUrl(): ?string
    {
        return $this->getBackTo();
    }

    /**
     * Alias for getImpersonatorGuardName().
     */
    public function getImpersonatorGuard(): ?string
    {
        return $this->getImpersonatorGuardName();
    }

    /**
     * Get the original impersonator user.
     */
    public function getImpersonator(): ?Authenticatable
    {
        $id = $this->getImpersonatorId();

        if ($id === null) {
            return null;
        }

        return $this->findUserById($id, $this->getImpersonatorGuardName());
    }

    /**
     * Take impersonation of a user.
     *
     * @param  Authenticatable  $from  The current user (impersonator)
     * @param  Authenticatable  $to  The user to impersonate
     * @param  string|null  $guardName  The guard to use for impersonation
     * @param  string|null  $backTo  URL to redirect back to when leaving
     */
    public function take(Authenticatable $from, Authenticatable $to, ?string $guardName = null, ?string $backTo = null): bool
    {
        $guardName = $guardName ?? $this->getDefaultGuard();

        try {
            $currentGuard = $this->getCurrentAuthGuardName();

            session()->put(self::SESSION_KEY, $from->getAuthIdentifier());
            session()->put(self::SESSION_GUARD, $currentGuard);
            session()->put(self::SESSION_GUARD_USING, $guardName);

            if ($backTo !== null) {
                session()->put(self::SESSION_BACK_TO, $backTo);
            }

            // Use quietLogout/quietLogin if available (custom guard), otherwise fallback
            $guard = $this->app['auth']->guard($currentGuard);

            if (method_exists($guard, 'quietLogout')) {
                $guard->quietLogout();
            }

            $targetGuard = $this->app['auth']->guard($guardName);

            if (method_exists($targetGuard, 'quietLogin')) {
                $targetGuard->quietLogin($to);
            } else {
                // Fallback: login without firing events
                $targetGuard->setUser($to);
                session()->put($this->getAuthSessionKey($guardName), $to->getAuthIdentifier());
            }

            // Update password hash in session to prevent AuthenticateSession middleware
            // from logging out the impersonated user (it validates password hash)
            $this->updatePasswordHashInSession($to);

            session()->save();

        } catch (Exception $e) {
            report($e);

            return false;
        }

        $this->app['events']->dispatch(new TakeImpersonation($from, $to));

        return true;
    }

    /**
     * Leave the current impersonation.
     */
    public function leave(): bool
    {
        if (! $this->isImpersonating()) {
            return false;
        }

        try {
            $impersonated = $this->app['auth']->guard($this->getImpersonatorGuardUsingName())->user();
            $impersonator = $this->getImpersonator();

            if ($impersonator === null) {
                $this->clear();

                return false;
            }

            $currentGuard = $this->app['auth']->guard($this->getCurrentAuthGuardName());
            $impersonatorGuard = $this->app['auth']->guard($this->getImpersonatorGuardName());

            // Use quiet methods if available
            if (method_exists($currentGuard, 'quietLogout')) {
                $currentGuard->quietLogout();
            }

            if (method_exists($impersonatorGuard, 'quietLogin')) {
                $impersonatorGuard->quietLogin($impersonator);
            } else {
                // Fallback
                $impersonatorGuard->setUser($impersonator);
                $guardName = $this->getImpersonatorGuardName() ?? 'web';
                session()->put($this->getAuthSessionKey($guardName), $impersonator->getAuthIdentifier());
            }

            // Update password hash in session for the restored user
            $this->updatePasswordHashInSession($impersonator);

            $this->clear();

            session()->save();

            if ($impersonated !== null) {
                $this->app['events']->dispatch(new LeaveImpersonation($impersonator, $impersonated));
            }

        } catch (Exception $e) {
            report($e);

            return false;
        }

        return true;
    }

    /**
     * Clear impersonation session data.
     */
    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
        session()->forget(self::SESSION_GUARD);
        session()->forget(self::SESSION_GUARD_USING);
        session()->forget(self::SESSION_BACK_TO);
    }

    /**
     * Find a user by ID using the specified guard's user provider.
     */
    public function findUserById(mixed $id, ?string $guardName = null): ?Authenticatable
    {
        $guardName = $guardName ?? $this->getDefaultGuard();
        $providerName = $this->app['config']->get("auth.guards.{$guardName}.provider");

        if (empty($providerName)) {
            return null;
        }

        try {
            $userProvider = $this->app['auth']->createUserProvider($providerName);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $userProvider?->retrieveById($id);
    }

    /**
     * Get the current authenticated guard name.
     */
    public function getCurrentAuthGuardName(): ?string
    {
        $guards = array_keys($this->app['config']->get('auth.guards', []));

        foreach ($guards as $guard) {
            if ($this->app['auth']->guard($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }

    /**
     * Get the default guard for impersonation.
     */
    public function getDefaultGuard(): string
    {
        return (string) config('filament-authz.impersonate.guard', 'web');
    }

    /**
     * Get the redirect URL for after leaving impersonation.
     * Always returns to the origin panel where impersonation began.
     */
    public function getLeaveRedirectTo(): ?string
    {
        return $this->getBackTo();
    }

    /**
     * Get the session key used by Laravel Auth for a guard.
     */
    private function getAuthSessionKey(string $guard): string
    {
        return 'login_' . $guard . '_' . sha1(SessionGuard::class);
    }

    /**
     * Update the password hash in session for the given user.
     *
     * This is required to prevent AuthenticateSession middleware from
     * logging out the user when it validates the password hash.
     */
    private function updatePasswordHashInSession(Authenticatable $user): void
    {
        if (! method_exists($user, 'getAuthPassword')) {
            return;
        }

        $passwordHash = $user->getAuthPassword();

        if (empty($passwordHash)) {
            return;
        }

        $guard = $this->app['auth']->guard();

        if (method_exists($guard, 'hashPasswordForCookie')) {
            $hashedPassword = $guard->hashPasswordForCookie($passwordHash);
        } else {
            $hashedPassword = $passwordHash;
        }

        $driver = $this->app['config']->get('auth.defaults.guard', 'web');
        session()->put('password_hash_' . $driver, $hashedPassword);
    }
}
