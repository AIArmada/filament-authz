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
use AIArmada\FilamentAuthz\Support\ImpersonationActorAuth;
use Illuminate\Contracts\Http\Kernel;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentAuthzServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-authz')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasRoutes(['web'])
            ->hasCommands([
                DiscoverCommand::class,
                GeneratePoliciesCommand::class,
                SeederCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        CommandProhibitor::register([
            GeneratePoliciesCommand::class,
            SeederCommand::class,
        ]);

        $this->app->scoped(FilamentAuthzPlugin::class);
        $this->app->scoped(ImpersonationActorAuth::class);
        $this->app->singleton(EntityDiscoveryService::class);
        $this->app->scoped(Authz::class, function ($app): Authz {
            return new Authz($app->make(PermissionKeyBuilder::class));
        });

        $this->registerOctaneListeners();
    }

    public function packageBooted(): void
    {
        $this->registerImpersonationBanner();
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
