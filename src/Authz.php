<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\Authz\Authz as BaseAuthz;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Authz extends BaseAuthz
{
    /** @var array<string, Collection<int, mixed>> */
    protected array $discoveryCache = [];

    /** @var array<string, array<string, string>> */
    protected array $permissionCache = [];

    public function getResources(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();
        $key = $panel?->getId() ?? 'default';

        if (! isset($this->discoveryCache[$key . '_resources'])) {
            $this->discoveryCache[$key . '_resources'] = $this->transformResources($panel);
        }

        /** @var Collection<int, array{type: string, class: class-string<resource>, subject: string, permissions: array<string, string>, actions: array<string, string>, label: string, model: class-string<Model>|null}> $resources */
        $resources = $this->discoveryCache[$key . '_resources'];

        return $resources;
    }

    public function getPages(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();
        $key = $panel?->getId() ?? 'default';

        if (! isset($this->discoveryCache[$key . '_pages'])) {
            $this->discoveryCache[$key . '_pages'] = $this->transformPages($panel);
        }

        /** @var Collection<int, array{type: string, class: class-string<Page>, permission: string, label: string}> $pages */
        $pages = $this->discoveryCache[$key . '_pages'];

        return $pages;
    }

    public function getWidgets(?Panel $panel = null): Collection
    {
        $panel ??= Filament::getCurrentPanel();
        $key = $panel?->getId() ?? 'default';

        if (! isset($this->discoveryCache[$key . '_widgets'])) {
            $this->discoveryCache[$key . '_widgets'] = $this->transformWidgets($panel);
        }

        /** @var Collection<int, array{type: string, class: class-string<Widget>, permission: string, label: string}> $widgets */
        $widgets = $this->discoveryCache[$key . '_widgets'];

        return $widgets;
    }

    public function getPanels(): Collection
    {
        if (! isset($this->discoveryCache['panels'])) {
            $this->discoveryCache['panels'] = $this->transformPanels();
        }

        return $this->discoveryCache['panels'];
    }

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

        foreach ($this->getPanels() as $p) {
            $permissions[] = $p['permission'];
        }

        $permissions = array_merge($permissions, array_keys($this->getCustomPermissions()));

        return array_values(array_unique($permissions));
    }

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

    public function getResourcePermissions(string $resourceClass, ?Panel $panel = null): array
    {
        $resource = $this->getResources($panel)->first(fn (array $r): bool => $r['class'] === $resourceClass);

        return $resource['permissions'] ?? [];
    }

    public function clearCache(): void
    {
        parent::clearCache();
        $this->discoveryCache = [];
        $this->permissionCache = [];
    }

    protected function transformPanels(): Collection
    {
        $excluded = (array) config('filament-authz.panels.exclude', []);
        $prefix = (string) config('filament-authz.panels.prefix', 'panel');

        return collect(Filament::getPanels())
            ->filter(fn (Panel $p): bool => ! in_array($p->getId(), $excluded, true))
            ->map(function (Panel $p) use ($prefix): array {
                $permission = $this->buildPermissionKey($prefix, $p->getId());

                return [
                    'type' => 'panel',
                    'id' => $p->getId(),
                    'permission' => $permission,
                    'label' => Str::headline($p->getId()),
                ];
            })
            ->values();
    }

    protected function transformResources(?Panel $panel): Collection
    {
        if ($panel === null) {
            return collect();
        }

        $excluded = (array) config('filament-authz.resources.exclude', []);

        $resources = collect($panel->getResources())
            ->filter(fn (string $resource): bool => ! in_array($resource, $excluded, true))
            ->map(function (string $resource): array {
                /** @var class-string<resource> $resource */
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

        return $resources;
    }

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
                /** @var class-string<Page> $page */
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

    protected function getResourceSubject(string $resource): string
    {
        $subject = (string) config('filament-authz.resources.subject', 'model');

        if ($subject === 'model' && method_exists($resource, 'getModel')) {
            return class_basename($resource::getModel());
        }

        return str(class_basename($resource))->beforeLast('Resource')->toString();
    }

    protected function getResourceLabel(string $resource): string
    {
        if (method_exists($resource, 'getModelLabel')) {
            return $resource::getModelLabel();
        }

        return str(class_basename($resource))->beforeLast('Resource')->headline()->toString();
    }

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
