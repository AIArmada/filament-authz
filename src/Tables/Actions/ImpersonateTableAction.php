<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Tables\Actions;

use AIArmada\FilamentAuthz\Services\ImpersonateManager;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Table action to impersonate a user.
 *
 * Add this action to the UserResource table to allow impersonation.
 *
 * @example
 * ```php
 * use AIArmada\FilamentAuthz\Tables\Actions\ImpersonateTableAction;
 *
 * public static function table(Table $table): Table
 * {
 *     return $table
 *         ->actions([
 *             ImpersonateTableAction::make(),
 *         ]);
 * }
 * ```
 */
class ImpersonateTableAction extends Action
{
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
            ->modalHeading(__('filament-authz::filament-authz.impersonate.modal_heading'))
            ->modalDescription(__('filament-authz::filament-authz.impersonate.modal_description'))
            ->modalSubmitActionLabel(__('filament-authz::filament-authz.impersonate.confirm'))
            ->form([
                Select::make('redirect_to')
                    ->label(__('filament-authz::filament-authz.impersonate.redirect_label'))
                    ->options(fn (): array => $this->getRedirectOptions())
                    ->default('/')
                    ->required()
                    ->native(true)
                    ->helperText(__('filament-authz::filament-authz.impersonate.redirect_helper')),
            ])
            ->visible(fn (Model $record): bool => $this->canImpersonate($record))
            ->action(fn (Model $record, array $data) => $this->impersonate($record, $data['redirect_to'] ?? '/'));
    }

    /**
     * @return array<string, string>
     */
    protected function getRedirectOptions(): array
    {
        $options = [
            '/' => __('filament-authz::filament-authz.impersonate.redirect_frontpage'),
        ];

        foreach (Filament::getPanels() as $panel) {
            /** @var Panel $panel */
            $panelId = $panel->getId();
            $panelPath = $panel->getPath();
            $panelName = str($panelId)->title()->replace('-', ' ')->toString();

            $options['/' . mb_ltrim($panelPath, '/')] = $panelName . ' ' . __('filament-authz::filament-authz.impersonate.redirect_panel_suffix');
        }

        return $options;
    }

    protected function canImpersonate(Model $record): bool
    {
        if (! config('filament-authz.impersonate.enabled', true)) {
            return false;
        }

        $currentUser = Filament::auth()->user();
        $manager = app(ImpersonateManager::class);

        if ($currentUser === null) {
            return false;
        }

        if ($currentUser->getAuthIdentifier() === $record->getKey()) {
            return false;
        }

        if ($manager->isImpersonating()) {
            return false;
        }

        if (method_exists($currentUser, 'canImpersonate') && ! $currentUser->canImpersonate()) {
            return false;
        }

        if (method_exists($record, 'canBeImpersonated') && ! $record->canBeImpersonated()) {
            return false;
        }

        $superAdminRole = config('filament-authz.super_admin_role');

        if ($superAdminRole && method_exists($currentUser, 'hasRole')) {
            return $currentUser->hasRole($superAdminRole);
        }

        return false;
    }

    /**
     * Impersonate the given user.
     *
     * Returns true to signal to Filament that the action completed successfully
     * and should not continue processing (which would trigger another Livewire request).
     */
    protected function impersonate(Model $record, string $redirectTo = '/'): bool
    {
        if (! $record instanceof Authenticatable) {
            return false;
        }

        $currentUser = Filament::auth()->user();
        $guard = config('filament-authz.impersonate.guard', 'web');
        $manager = app(ImpersonateManager::class);

        if ($currentUser === null) {
            return false;
        }

        $backTo = request()->header('referer') ?? Filament::getUrl();

        $success = $manager->take($currentUser, $record, $guard, $backTo);

        if ($success) {
            // Use Livewire's client-side redirect to avoid CSRF 419 errors
            // This queues a JavaScript redirect that happens AFTER the current request completes
            $this->redirect($redirectTo, navigate: false);

            // Return true to signal action completed - prevents Filament from triggering more Livewire requests
            return true;
        }

        return false;
    }
}
