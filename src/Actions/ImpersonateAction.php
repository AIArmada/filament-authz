<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Actions;

use AIArmada\FilamentAuthz\Services\ImpersonateManager;
use AIArmada\FilamentAuthz\Support\ImpersonationScopeGuard;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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
    /**
     * @deprecated Use ImpersonateManager::SESSION_KEY instead
     */
    public const SESSION_KEY = 'filament_authz_impersonator_id';

    /**
     * @deprecated Use ImpersonateManager::SESSION_GUARD instead
     */
    public const SESSION_GUARD_KEY = 'filament_authz_impersonator_guard';

    /**
     * @deprecated Use ImpersonateManager::SESSION_BACK_TO instead
     */
    public const SESSION_BACK_TO_KEY = 'filament_authz_impersonator_back_to';

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

        // Scope check is orthogonal to actor authorization — always run it.
        if (! ImpersonationScopeGuard::canAccessTarget($targetUser)) {
            return false;
        }

        return $this->isActorAuthorizedToImpersonate($currentUser);
    }

    /**
     * Verify the acting user has permission to perform impersonation.
     * Called both from canImpersonate() (visibility) and impersonate() (execution).
     */
    private function isActorAuthorizedToImpersonate(Authenticatable $actor): bool
    {
        if (method_exists($actor, 'canImpersonate') && $actor->canImpersonate()) {
            return true;
        }

        $superAdminRole = config('filament-authz.super_admin_role');

        if ($superAdminRole && method_exists($actor, 'hasRole')) {
            return (bool) $actor->hasRole($superAdminRole);
        }

        return false;
    }

    protected function impersonate(): void
    {
        $currentUser = Filament::auth()->user();
        $targetUser = $this->getTargetUser();
        $guard = config('filament-authz.impersonate.guard', 'web');
        $manager = app(ImpersonateManager::class);

        if ($currentUser === null || $targetUser === null) {
            return;
        }

        if (! $targetUser instanceof Authenticatable) {
            return;
        }

        if (method_exists($targetUser, 'canBeImpersonated') && ! $targetUser->canBeImpersonated()) {
            return;
        }

        if (! ImpersonationScopeGuard::canAccessTarget($targetUser)) {
            return;
        }

        // Re-validate actor authorization in the execution path (defense-in-depth).
        if (! $this->isActorAuthorizedToImpersonate($currentUser)) {
            return;
        }

        $backTo = request()->header('referer') ?? Filament::getUrl();

        $success = $manager->take($currentUser, $targetUser, $guard, $backTo);

        if ($success) {
            // Redirect is now handled by the modal form's redirect_to field
            // The manager->take() handles session storage
        }
    }

    /**
     * @deprecated Use app(ImpersonateManager::class)->isImpersonating() instead
     */
    public static function isImpersonating(): bool
    {
        return app(ImpersonateManager::class)->isImpersonating();
    }

    /**
     * @deprecated Use app(ImpersonateManager::class)->getImpersonatorId() instead
     */
    public static function getImpersonatorId(): mixed
    {
        return app(ImpersonateManager::class)->getImpersonatorId();
    }

    /**
     * @deprecated Use app(ImpersonateManager::class)->leave() instead
     */
    public static function leave(): ?string
    {
        $manager = app(ImpersonateManager::class);
        $backTo = $manager->getBackToUrl();
        $manager->leave();

        return $backTo;
    }

    /**
     * @deprecated Use app(ImpersonateManager::class)->getImpersonatorGuard() instead
     */
    public static function getImpersonatorGuard(): ?string
    {
        return app(ImpersonateManager::class)->getImpersonatorGuard();
    }
}
