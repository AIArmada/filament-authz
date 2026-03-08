<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;
use AIArmada\FilamentAuthz\Console\SuperAdminCommand;
use AIArmada\FilamentAuthz\Console\SyncAuthzCommand;
use AIArmada\FilamentAuthz\Guard\SessionGuard;
use AIArmada\FilamentAuthz\Http\Middleware\ImpersonationBannerMiddleware;
use AIArmada\FilamentAuthz\Models\Permission as AuthzPermission;
use AIArmada\FilamentAuthz\Models\Role as AuthzRole;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\ImpersonateManager;
use AIArmada\FilamentAuthz\Services\PermissionKeyBuilder;
use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;
use AIArmada\FilamentAuthz\Support\AuthzScopeTeamResolver;
use AIArmada\FilamentAuthz\Support\OwnerContextTeamResolver;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Spatie\Permission\Contracts\PermissionsTeamResolver;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Authz Service Provider.
 *
 * Features:
 * - Cleaner service registration
 * - Proper singleton bindings
 * - Modular command registration
 * - Laravel Octane compatibility
 */
class FilamentAuthzServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-authz.php', 'filament-authz');

        $this->configureSpatiePermissions();

        $this->app->singleton(FilamentAuthzPlugin::class);
        $this->app->singleton(WildcardPermissionResolver::class);
        $this->app->singleton(EntityDiscoveryService::class);
        $this->app->singleton(PermissionKeyBuilder::class);

        $this->app->singleton(Authz::class, function ($app): Authz {
            return new Authz(
                $app->make(EntityDiscoveryService::class),
                $app->make(PermissionKeyBuilder::class)
            );
        });

        $this->app->singleton(ImpersonateManager::class, function ($app): ImpersonateManager {
            return new ImpersonateManager($app);
        });

        $this->app->alias(ImpersonateManager::class, 'impersonate');

        $this->registerTeamResolver();
        $this->registerAuthDriver();
        $this->registerOctaneListeners();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-authz');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-authz');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerImpersonationBanner();
        $this->registerBladeDirectives();
        $this->registerImpersonationEventListeners();

        $this->publishes([
            __DIR__ . '/../config/filament-authz.php' => config_path('filament-authz.php'),
        ], 'filament-authz-config');

        $this->publishes([
            __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/filament-authz'),
        ], 'filament-authz-translations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-authz'),
        ], 'filament-authz-views');

        $this->registerGateHooks();
        $this->registerCommands();
    }

    protected function registerGateHooks(): void
    {
        $superAdminRole = (string) config('filament-authz.super_admin_role');

        if ($superAdminRole !== '') {
            Gate::before(static function ($user, string $ability) use ($superAdminRole) {
                if (! method_exists($user, 'hasRole')) {
                    return null;
                }

                $registrar = app(PermissionRegistrar::class);
                $teams = $registrar->teams;
                $registrar->teams = false;

                try {
                    return $user->hasRole($superAdminRole) ? true : null;
                } finally {
                    $registrar->teams = $teams;
                }
            });
        }

        if (config('filament-authz.wildcard_permissions', true)) {
            Gate::before(function ($user, string $ability) {
                if (! method_exists($user, 'getAllPermissions')) {
                    return null;
                }

                $resolver = app(WildcardPermissionResolver::class);
                $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

                foreach ($userPermissions as $permission) {
                    if ($resolver->isWildcard($permission) && $resolver->matches($permission, $ability)) {
                        return true;
                    }
                }

                return null;
            });
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DiscoverCommand::class,
                GeneratePoliciesCommand::class,
                SeederCommand::class,
                SuperAdminCommand::class,
                SyncAuthzCommand::class,
            ]);
        }
    }

    private function configureSpatiePermissions(): void
    {
        if (config('permission.models.permission') === SpatiePermission::class) {
            config()->set('permission.models.permission', AuthzPermission::class);
        }

        if (config('permission.models.role') === SpatieRole::class) {
            config()->set('permission.models.role', AuthzRole::class);
        }
    }

    private function registerTeamResolver(): void
    {
        if (config('filament-authz.authz_scopes.enabled', false)) {
            $this->app->singleton(PermissionsTeamResolver::class, AuthzScopeTeamResolver::class);

            return;
        }

        if (! class_exists(\AIArmada\CommerceSupport\Support\OwnerContext::class)) {
            return;
        }

        if (! config('permission.teams', false)) {
            return;
        }

        $this->app->singleton(PermissionsTeamResolver::class, OwnerContextTeamResolver::class);
    }

    /**
     * Register Octane listeners to clear caches between requests.
     *
     * This ensures fresh permission/role data on each Octane request
     * by resetting Spatie permission cache and Authz discovery cache.
     */
    private function registerOctaneListeners(): void
    {
        if (! class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(
            \Laravel\Octane\Events\RequestReceived::class,
            function (): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                if ($this->app->has(Authz::class)) {
                    app(Authz::class)->clearCache();
                }
            }
        );
    }

    private function registerImpersonationBanner(): void
    {
        if (! config('filament-authz.impersonate.enabled', true)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup('web', ImpersonationBannerMiddleware::class);
    }

    /**
     * Register custom auth driver with quietLogin/quietLogout support.
     */
    private function registerAuthDriver(): void
    {
        /** @var AuthManager $auth */
        $auth = $this->app['auth'];

        $auth->extend('session', function (Application $app, string $name, array $config) use ($auth) {
            $provider = $auth->createUserProvider($config['provider'] ?? null);

            $guard = new SessionGuard(
                $name,
                $provider,
                $app['session.store'],
                $app['request'] ?? null
            );

            if (method_exists($guard, 'setCookieJar')) {
                $guard->setCookieJar($app['cookie']);
            }

            if (method_exists($guard, 'setDispatcher')) {
                $guard->setDispatcher($app['events']);
            }

            if (method_exists($guard, 'setRequest')) {
                $guard->setRequest($app->refresh('request', $guard, 'setRequest'));
            }

            if (isset($config['remember'])) {
                $guard->setRememberDuration($config['remember']);
            }

            return $guard;
        });
    }

    /**
     * Register Blade directives for impersonation.
     */
    private function registerBladeDirectives(): void
    {
        $this->app->afterResolving('blade.compiler', function (BladeCompiler $blade): void {
            $blade->directive('impersonating', function (?string $guard = null): string {
                return "<?php if (\\AIArmada\\FilamentAuthz\\is_impersonating({$guard})) : ?>";
            });

            $blade->directive('endImpersonating', function (): string {
                return '<?php endif; ?>';
            });

            $blade->directive('canImpersonate', function (?string $guard = null): string {
                return "<?php if (\\AIArmada\\FilamentAuthz\\can_impersonate({$guard})) : ?>";
            });

            $blade->directive('endCanImpersonate', function (): string {
                return '<?php endif; ?>';
            });

            $blade->directive('canBeImpersonated', function (string $expression): string {
                $args = preg_split("/,(\s+)?/", $expression);
                $guard = $args[1] ?? 'null';

                return "<?php if (\\AIArmada\\FilamentAuthz\\can_be_impersonated({$args[0]}, {$guard})) : ?>";
            });

            $blade->directive('endCanBeImpersonated', function (): string {
                return '<?php endif; ?>';
            });
        });
    }

    /**
     * Clear impersonation data on real login/logout events.
     */
    private function registerImpersonationEventListeners(): void
    {
        Event::listen(Login::class, function (): void {
            app(ImpersonateManager::class)->clear();
        });

        Event::listen(Logout::class, function (): void {
            app(ImpersonateManager::class)->clear();
        });
    }
}
