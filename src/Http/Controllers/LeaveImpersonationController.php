<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Http\Controllers;

use AIArmada\FilamentAuthz\Services\ImpersonateManager;
use Illuminate\Http\RedirectResponse;

class LeaveImpersonationController
{
    public function __invoke(ImpersonateManager $manager): RedirectResponse
    {
        if (! $manager->isImpersonating()) {
            return redirect('/');
        }

        $backTo = $manager->getBackToUrl();
        $manager->leave();

        // Always redirect back to origin panel where impersonation began
        $redirectTo = self::sanitizeBackToUrl($backTo);

        return redirect($redirectTo)->with('status', __('filament-authz::filament-authz.impersonate.left_message'));
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
}
