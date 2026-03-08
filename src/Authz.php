<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\PermissionKeyBuilder;
use AIArmada\FilamentAuthz\Support\AuthzScopeResolver;
use Closure;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

/**
 * Main Authz service providing entity discovery, permission building, and configuration access.
 *
 * Features: Caches everything, supports wildcards, cleaner API.
 */
class Authz
{
    protected ?Closure $customPermissionKeyBuilder = null;

    /** @var array<string, Collection<int, array<string, mixed>>> */
    protected array $discoveryCache = [];

    /** @var array<string, array<string, string>> */
    protected array $permissionCache = [];

    public function __construct(
        protected EntityDiscoveryService $discovery,
        protected PermissionKeyBuilder $keyBuilder
    ) {}

    /**
     * Customize how permission keys are built.
     */
    public function buildPermissionKeyUsing(Closure $callback): static
    {
        $this->customPermissionKeyBuilder = $callback;

        return $this;
    }

    /**
     * Build a permission key using configured settings or custom builder.
     */
    public function buildPermissionKey(string $subject, string $action): string
    {
        if ($this->customPermissionKeyBuilder !== null) {
            return ($this->customPermissionKeyBuilder)($subject, $action);
        }

        return $this->keyBuilder->build($subject, $action);
    }

    /**
     * Get all discovered resources for a panel.
     *
     * @return Collection<int, array{type: string, class: class-string, permissions: array<string, string>, label: string}>
     */
    public function getResources(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();
        $key = $panel?->getId() ?? 'default';

        if (! isset($this->discoveryCache[$key . '_resources'])) {
            $this->discoveryCache[$key . '_resources'] = $this->transformResources($panel);
        }

        return $this->discoveryCache[$key . '_resources'];
    }

    /**
     * Get all discovered pages for a panel.
     *
     * @return Collection<int, array{type: string, class: class-string, permission: string, label: string}>
     */
    public function getPages(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();
        $key = $panel?->getId() ?? 'default';

        if (! isset($this->discoveryCache[$key . '_pages'])) {
            $this->discoveryCache[$key . '_pages'] = $this->transformPages($panel);
        }

        return $this->discoveryCache[$key . '_pages'];
    }

    /**
     * Get all discovered widgets for a panel.
     *
     * @return Collection<int, array{type: string, class: class-string, permission: string, label: string}>
     */
    public function getWidgets(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();
        $key = $panel?->getId() ?? 'default';

        if (! isset($this->discoveryCache[$key . '_widgets'])) {
            $this->discoveryCache[$key . '_widgets'] = $this->transformWidgets($panel);
        }

        return $this->discoveryCache[$key . '_widgets'];
    }

    /**
     * Get custom permissions from config.
     *
     * @return array<string, string>
     */
    public function getCustomPermissions(): array
    {
        $custom = (array) config('filament-authz.custom_permissions', []);
        $result = [];

        foreach ($custom as $key => $label) {
            if (is_int($key)) {
                $result[$label] = str($label)->headline()->toString();
            } else {
                $result[$key] = $label;
            }
        }

        return $result;
    }

    /**
     * Get all entity permissions as a flat array.
     *
     * @return list<string>
     */
    public function getAllPermissions(?Panel $panel = null): array
    {
        $permissions = [];

        foreach ($this->getResources($panel) as $resource) {
            $permissions = array_merge($permissions, array_keys($resource['permissions']));
        }

        foreach ($this->getPages($panel) as $page) {
            $permissions[] = $page['permission'];
        }

        foreach ($this->getWidgets($panel) as $widget) {
            $permissions[] = $widget['permission'];
        }

        $permissions = array_merge($permissions, array_keys($this->getCustomPermissions()));

        return array_values(array_unique($permissions));
    }

    /**
     * Get permission for a specific page class.
     *
     * @param  class-string<Page>  $pageClass
     */
    public function getPagePermission(string $pageClass, ?Panel $panel = null): ?string
    {
        $cacheKey = $pageClass . '_' . ($panel?->getId() ?? 'default');

        if (isset($this->permissionCache['page'][$cacheKey])) {
            return $this->permissionCache['page'][$cacheKey];
        }

        $page = $this->getPages($panel)->first(fn (array $p): bool => $p['class'] === $pageClass);

        $permission = $page['permission'] ?? null;
        $this->permissionCache['page'][$cacheKey] = $permission;

        return $permission;
    }

    /**
     * Get permission for a specific widget class.
     *
     * @param  class-string<Widget>  $widgetClass
     */
    public function getWidgetPermission(string $widgetClass, ?Panel $panel = null): ?string
    {
        $cacheKey = $widgetClass . '_' . ($panel?->getId() ?? 'default');

        if (isset($this->permissionCache['widget'][$cacheKey])) {
            return $this->permissionCache['widget'][$cacheKey];
        }

        $widget = $this->getWidgets($panel)->first(fn (array $w): bool => $w['class'] === $widgetClass);

        $permission = $widget['permission'] ?? null;
        $this->permissionCache['widget'][$cacheKey] = $permission;

        return $permission;
    }

    /**
     * Get permissions for a specific resource class.
     *
     * @param  class-string<resource>  $resourceClass
     * @return array<string, string>
     */
    public function getResourcePermissions(string $resourceClass, ?Panel $panel = null): array
    {
        $resource = $this->getResources($panel)->first(fn (array $r): bool => $r['class'] === $resourceClass);

        return $resource['permissions'] ?? [];
    }

    /**
     * Resolve a scopeable model or scope to an authz scope ID.
     */
    public function resolveScopeId(mixed $scope): string | int | null
    {
        return AuthzScopeResolver::resolveId($scope);
    }

    /**
     * Run a callback within a scope context.
     */
    public function withScope(mixed $scope, callable $callback, ?Authorizable $user = null): mixed
    {
        $previousScope = getPermissionsTeamId();
        $scopeId = $this->resolveScopeId($scope);

        setPermissionsTeamId($scopeId);
        $this->flushPermissionCache($user);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previousScope);
            $this->flushPermissionCache($user);
        }
    }

    /**
     * Check a permission within a scope context.
     */
    public function userCanInScope(Authorizable $user, string $ability, mixed $scope): bool
    {
        return (bool) $this->withScope($scope, fn (): bool => $user->can($ability), $user);
    }

    /**
     * Check a permission across all scopes.
     */
    public function userHasPermissionAcrossScopes(Authorizable $user, string $ability): bool
    {
        return (bool) $this->withoutTeams(fn (): bool => $user->can($ability), $user);
    }

    /**
     * Clear all caches.
     */
    public function clearCache(): void
    {
        $this->discoveryCache = [];
        $this->permissionCache = [];
    }

    protected function flushPermissionCache(?Authorizable $user = null): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $authUser = $user ?? Auth::user();

        if ($authUser instanceof Model) {
            $authUser->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    protected function withoutTeams(callable $callback, ?Authorizable $user = null): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $teams = $registrar->teams;

        $registrar->teams = false;
        $this->flushPermissionCache($user);

        try {
            return $callback();
        } finally {
            $registrar->teams = $teams;
            $this->flushPermissionCache($user);
        }
    }

    /**
     * Transform resources into permission structure.
     *
     * @return Collection<int, array{type: string, class: class-string, permissions: array<string, string>, label: string}>
     */
    protected function transformResources(?Panel $panel): Collection
    {
        if ($panel === null) {
            return collect();
        }

        $excluded = (array) config('filament-authz.resources.exclude', []);

        return collect($panel->getResources())
            ->filter(fn (string $resource): bool => ! in_array($resource, $excluded, true))
            ->map(function (string $resource): array {
                $subject = $this->getResourceSubject($resource);
                $label = $this->getResourceLabel($resource);
                $actions = $this->getResourceActions($resource);

                $permissions = [];
                $actionMap = [];
                foreach ($actions as $action) {
                    $key = $this->buildPermissionKey($subject, $action);
                    $permissions[$key] = $this->getActionLabel($action);
                    $actionMap[$action] = $key;
                }

                return [
                    'type' => 'resource',
                    'class' => $resource,
                    'subject' => $subject,
                    'permissions' => $permissions,
                    'actions' => $actionMap,
                    'label' => $label,
                    'model' => method_exists($resource, 'getModel') ? $resource::getModel() : null,
                ];
            })
            ->values();
    }

    /**
     * Transform pages into permission structure.
     *
     * @return Collection<int, array{type: string, class: class-string, permission: string, label: string}>
     */
    protected function transformPages(?Panel $panel): Collection
    {
        if ($panel === null) {
            return collect();
        }

        $excluded = (array) config('filament-authz.pages.exclude', []);
        $prefix = (string) config('filament-authz.pages.prefix', 'page');

        return collect($panel->getPages())
            ->filter(fn (string $page): bool => ! in_array($page, $excluded, true))
            ->map(function (string $page) use ($prefix): array {
                $subject = str(class_basename($page))->toString();
                $permission = $this->buildPermissionKey($prefix, $subject);

                return [
                    'type' => 'page',
                    'class' => $page,
                    'permission' => $permission,
                    'label' => str(class_basename($page))->headline()->toString(),
                ];
            })
            ->values();
    }

    /**
     * Transform widgets into permission structure.
     *
     * @return Collection<int, array{type: string, class: class-string, permission: string, label: string}>
     */
    protected function transformWidgets(?Panel $panel): Collection
    {
        if ($panel === null) {
            return collect();
        }

        $excluded = (array) config('filament-authz.widgets.exclude', []);
        $prefix = (string) config('filament-authz.widgets.prefix', 'widget');

        return collect($panel->getWidgets())
            ->filter(fn (string $widget): bool => ! in_array($widget, $excluded, true))
            ->map(function (string $widget) use ($prefix): array {
                $subject = str(class_basename($widget))->toString();
                $permission = $this->buildPermissionKey($prefix, $subject);

                return [
                    'type' => 'widget',
                    'class' => $widget,
                    'permission' => $permission,
                    'label' => str(class_basename($widget))->headline()->toString(),
                ];
            })
            ->values();
    }

    /**
     * @param  class-string<resource>  $resource
     */
    protected function getResourceSubject(string $resource): string
    {
        $subject = (string) config('filament-authz.resources.subject', 'model');

        if ($subject === 'model' && method_exists($resource, 'getModel')) {
            return class_basename($resource::getModel());
        }

        return str(class_basename($resource))->beforeLast('Resource')->toString();
    }

    /**
     * @param  class-string<resource>  $resource
     */
    protected function getResourceLabel(string $resource): string
    {
        if (method_exists($resource, 'getModelLabel')) {
            return $resource::getModelLabel();
        }

        return str(class_basename($resource))->beforeLast('Resource')->headline()->toString();
    }

    /**
     * @param  class-string<resource>  $resource
     * @return list<string>
     */
    protected function getResourceActions(string $resource): array
    {
        $actions = (array) config('filament-authz.resources.actions', []);
        $extras = (array) config('filament-authz.resources.extra_actions', []);

        $extraActions = (array) ($extras[$resource] ?? []);

        return array_values(array_unique(array_merge($actions, $extraActions)));
    }

    protected function getActionLabel(string $action): string
    {
        $labels = (array) config('filament-authz.resources.action_labels', []);

        if (isset($labels[$action])) {
            return (string) $labels[$action];
        }

        return str($action)->headline()->toString();
    }
}
