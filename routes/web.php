<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Http\Controllers\ImpersonateController;
use AIArmada\FilamentAuthz\Http\Controllers\LeaveImpersonationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('filament-authz')
    ->group(function (): void {
        Route::post('impersonate/{userId}', ImpersonateController::class)
            ->name('filament-authz.impersonate');

        Route::get('impersonate/leave', LeaveImpersonationController::class)
            ->name('filament-authz.impersonate.leave');
    });
