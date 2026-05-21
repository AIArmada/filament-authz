<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Actions;

use AIArmada\FilamentAuthz\Services\ImpersonateManager;
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
            ->visible(fn (): bool => ImpersonateAction::isImpersonating())
            ->action(function (): void {
                $backTo = app(ImpersonateManager::class)->getBackToUrl();

                ImpersonateAction::leave();

                $this->redirect(self::sanitizeBackToUrl($backTo));
            });
    }

    private static function sanitizeBackToUrl(?string $url): string
    {
        if (! is_string($url) || $url === '') {
            return '/';
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['host'])) {
            return '/';
        }

        $requestHost = request()->getHost();

        if (mb_strtolower((string) $parsed['host']) !== mb_strtolower($requestHost)) {
            return '/';
        }

        return $url;
    }

    public function asMenuItem(): MenuItem
    {
        return MenuItem::make()
            ->label(__('filament-authz::filament-authz.impersonate.leave'))
            ->icon('heroicon-o-arrow-left-on-rectangle')
            ->color('danger')
            ->visible(fn (): bool => ImpersonateAction::isImpersonating())
            ->postAction(route('filament-authz.impersonate.leave'));
    }
}
