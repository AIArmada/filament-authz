<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\Authz\Services\PermissionKeyBuilder;
use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;
use AIArmada\FilamentAuthz\Http\Middleware\ImpersonationBannerMiddleware;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;

class FilamentAuthzServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-authz.php', 'filament-authz');

        $this->app->singleton(FilamentAuthzPlugin::class);
        $this->app->singleton(EntityDiscoveryService::class);
        $this->app->singleton(Authz::class, function ($app): Authz {
            return new Authz($app->make(PermissionKeyBuilder::class));
        });

        $this->registerOctaneListeners();
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-authz');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->registerImpersonationBanner();

        $this->publishes([
            __DIR__ . '/../config/filament-authz.php' => config_path('filament-authz.php'),
        ], 'filament-authz-config');

        $this->publishes([
            __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/filament-authz'),
        ], 'filament-authz-translations');

        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DiscoverCommand::class,
                GeneratePoliciesCommand::class,
                SeederCommand::class,
            ]);
        }
    }

    private function registerImpersonationBanner(): void
    {
        if (! config('filament-authz.impersonate.enabled', true)) {
            return;
        }

        $this->app->make(Kernel::class)
            ->appendMiddlewareToGroup('web', ImpersonationBannerMiddleware::class);
    }

    private function registerOctaneListeners(): void
    {
        if (! class_exists(RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(RequestReceived::class, static function (): void {
            if (app()->has(Authz::class)) {
                app(Authz::class)->clearCache();
            }
        });
    }
}
