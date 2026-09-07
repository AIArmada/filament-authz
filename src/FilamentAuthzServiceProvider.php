<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\Authz\Services\PermissionKeyBuilder;
use AIArmada\Authz\Support\CommandProhibitor;
use AIArmada\CommerceSupport\Support\AuditableModelRegistry;
use AIArmada\CommerceSupport\Support\LoggableModelRegistry;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;
use AIArmada\FilamentAuthz\Http\Middleware\ImpersonationBannerMiddleware;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;

class FilamentAuthzServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-authz.php', 'filament-authz');

        CommandProhibitor::register([
            GeneratePoliciesCommand::class,
            SeederCommand::class,
        ]);

        $this->app->scoped(FilamentAuthzPlugin::class);
        $this->app->singleton(EntityDiscoveryService::class);
        $this->app->scoped(Authz::class, function ($app): Authz {
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
        $flush = static function (): void {
            OwnerContext::flushState();

            if (app()->bound(AuditableModelRegistry::class)) {
                app(AuditableModelRegistry::class)->flush();
            }

            if (app()->bound(LoggableModelRegistry::class)) {
                app(LoggableModelRegistry::class)->flush();
            }

            if (app()->has(Authz::class)) {
                app(Authz::class)->clearCache();
            }
        };

        if (class_exists(RequestReceived::class)) {
            $this->app['events']->listen(RequestReceived::class, $flush);
        }

        if (class_exists(RequestTerminated::class)) {
            $this->app['events']->listen(RequestTerminated::class, $flush);
        }
    }
}
