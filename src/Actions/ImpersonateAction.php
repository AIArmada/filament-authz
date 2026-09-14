<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Actions;

use AIArmada\Authz\Services\ImpersonateManager;
use AIArmada\Authz\Support\BackToUrlSanitizer;
use AIArmada\Authz\Support\ImpersonationScopeGuard;
use AIArmada\Authz\Support\UserRoleChecker;
use AIArmada\FilamentAuthz\Support\ImpersonationActorAuth;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Page action to impersonate a user.
 *
 * Use this action in a Filament resource page (EditRecord, ViewRecord).
 *
 * @example
 * ```php
 * use AIArmada\FilamentAuthz\Actions\ImpersonateAction;
 *
 * protected function getHeaderActions(): array
 * {
 *     return [
 *         ImpersonateAction::make()
 *             ->record($this->getRecord()),
 *     ];
 * }
 * ```
 */
class ImpersonateAction extends Action
{
    protected Model | Authenticatable | null $targetRecord = null;

    public static function getDefaultName(): ?string
    {
        return 'impersonate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('')
            ->tooltip(__('filament-authz::filament-authz.impersonate.action'))
            ->icon('heroicon-o-identification')
            ->color('warning')
            ->iconButton()
            ->requiresConfirmation()
            ->modalHeading(__('filament-authz::filament-authz.impersonate.modal_heading'))
            ->modalDescription(__('filament-authz::filament-authz.impersonate.modal_description'))
            ->modalSubmitActionLabel(__('filament-authz::filament-authz.impersonate.confirm'))
            ->visible(fn (): bool => $this->canImpersonate())
            ->action(fn () => $this->impersonate());
    }

    /**
     * @param  Model|Authenticatable|array<string, mixed>|Closure|string|null  $record
     */
    public function record(Model | Authenticatable | array | Closure | string | null $record): static
    {
        if ($record instanceof Model || $record instanceof Authenticatable) {
            $this->targetRecord = $record;
        }

        return parent::record($record);
    }

    protected function getTargetUser(): Model | Authenticatable | null
    {
        return $this->targetRecord;
    }

    protected function canImpersonate(): bool
    {
        if (! config('filament-authz.impersonate.enabled', true)) {
            return false;
        }

        $currentUser = Filament::auth()->user();
        $targetUser = $this->getTargetUser();
        $manager = app(ImpersonateManager::class);

        if ($currentUser === null || $targetUser === null) {
            return false;
        }

        if ($currentUser->getAuthIdentifier() === $targetUser->getAuthIdentifier()) {
            return false;
        }

        if ($manager->isImpersonating()) {
            return false;
        }

        if (method_exists($targetUser, 'canBeImpersonated') && ! $targetUser->canBeImpersonated()) {
            return false;
        }

        // Actor authorization is memoized per request; run it before the
        // per-target scope query so unauthorized actors skip that query.
        if (! $this->isActorAuthorizedToImpersonate($currentUser)) {
            return false;
        }

        // Scope check is orthogonal to actor authorization — always run it.
        return ImpersonationScopeGuard::canAccessTarget($targetUser);
    }

    /**
     * Verify the acting user has permission to perform impersonation.
     * Called both from canImpersonate() (visibility) and impersonate() (execution).
     */
    private function isActorAuthorizedToImpersonate(Authenticatable $actor): bool
    {
        return app(ImpersonationActorAuth::class)->isAuthorized($actor, function () use ($actor): bool {
            if (method_exists($actor, 'canImpersonate') && $actor->canImpersonate()) {
                return true;
            }

            $superAdminRole = config('authz.super_admin_role');

            if ($superAdminRole) {
                return UserRoleChecker::hasGlobalRole($actor, $superAdminRole);
            }

            return false;
        });
    }

    /**
     * Impersonate the target user.
     *
     * Returns true to signal to Filament that the action completed successfully
     * and should not continue processing (which would trigger another Livewire request).
     */
    protected function impersonate(): bool
    {
        $currentUser = Filament::auth()->user();
        $targetUser = $this->getTargetUser();
        $guard = config('authz.impersonate.guard', 'web');
        $manager = app(ImpersonateManager::class);

        if ($currentUser === null || $targetUser === null) {
            return false;
        }

        if (! $targetUser instanceof Authenticatable) {
            return false;
        }

        if (method_exists($targetUser, 'canBeImpersonated') && ! $targetUser->canBeImpersonated()) {
            return false;
        }

        if (! ImpersonationScopeGuard::canAccessTarget($targetUser)) {
            return false;
        }

        // Re-validate actor authorization in the execution path (defense-in-depth).
        if (! $this->isActorAuthorizedToImpersonate($currentUser)) {
            return false;
        }

        if ($manager->isImpersonating()) {
            return false;
        }

        $backTo = request()->header('referer') ?? Filament::getUrl();

        if (! $manager->take($currentUser, $targetUser, $guard, $backTo)) {
            $this->notifyImpersonationFailed();

            return false;
        }

        // take() rotates the session id and CSRF token, so a full redirect is
        // required — otherwise subsequent Livewire requests fail with a 419.
        $this->redirect(BackToUrlSanitizer::sanitize($backTo), navigate: false);

        return true;
    }

    private function notifyImpersonationFailed(): void
    {
        Notification::make()
            ->danger()
            ->title(__('filament-authz::filament-authz.impersonate.failed_title'))
            ->body(__('filament-authz::filament-authz.impersonate.failed_message'))
            ->send();
    }
}
