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
        $redirectTo = $backTo ?? '/';

        return redirect($redirectTo)->with('status', __('filament-authz::filament-authz.impersonate.left_message'));
    }
}
