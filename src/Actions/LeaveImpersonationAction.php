<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Actions;

use AIArmada\Authz\Services\ImpersonateManager;
use AIArmada\Authz\Support\BackToUrlSanitizer;
use Filament\Actions\Action;
use Filament\Navigation\MenuItem;

/**
 * Action to leave impersonation and return to the original user.
 *
 * @example
 * ```php
 * use AIArmada\FilamentAuthz\Actions\LeaveImpersonationAction;
 *
 * // In your panel provider:
 * ->userMenuItems([
 *     LeaveImpersonationAction::make()->asMenuItem(),
 * ])
 * ```
 */
class LeaveImpersonationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'leave-impersonation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-authz::filament-authz.impersonate.leave'))
            ->icon('heroicon-o-arrow-left-on-rectangle')
            ->color('danger')
            ->visible(fn (): bool => app(ImpersonateManager::class)->isImpersonating())
            ->action(function (): void {
                $manager = app(ImpersonateManager::class);
                $backTo = $manager->getBackTo();

                $manager->leave();

                $this->redirect(self::sanitizeBackToUrl($backTo));
            });
    }

    private static function sanitizeBackToUrl(?string $url): string
    {
        return BackToUrlSanitizer::sanitize($url);
    }

    public function asMenuItem(): MenuItem
    {
        return MenuItem::make()
            ->label(__('filament-authz::filament-authz.impersonate.leave'))
            ->icon('heroicon-o-arrow-left-on-rectangle')
            ->color('danger')
            ->visible(fn (): bool => app(ImpersonateManager::class)->isImpersonating())
            ->postAction(route('filament-authz.impersonate.leave'));
    }
}
