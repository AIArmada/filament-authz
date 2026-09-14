<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\UserResource;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Throwable;

/**
 * Filament Authz Plugin with comprehensive fluent API.
 *
 * Features:
 * - Multi-panel support with per-panel configuration
 * - Tenant-scoped permissions (optional)
 * - Central app mode for multi-tenant architectures
 * - Customizable layout (grid columns, checkbox columns, section spans)
 * - Localized permission labels
 */
class FilamentAuthzPlugin implements Plugin
{
    use EvaluatesClosures;

    public const PLUGIN_ID = 'aiarmada-filament-authz';

    protected ?Panel $panel = null;

    protected bool | Closure $registerRoleResource = true;

    protected bool | Closure $registerPermissionResource = true;

    protected string | Closure | null $userRoleScopeMode = null;

    /** @var array<string, string> | Closure | null */
    protected array | Closure | null $roleScopeOptions = null;

    protected string | Closure | null $navigationGroup = null;

    protected string | Closure | null $navigationIcon = null;

    protected string | Closure | null $activeNavigationIcon = null;

    protected string | Closure | null $navigationLabel = null;

    protected int | Closure | null $navigationSort = null;

    protected bool | Closure | null $registerNavigation = null;

    protected string | Closure | null $navigationBadge = null;

    protected string | array | Closure | null $navigationBadgeColor = null;

    protected string | Closure | null $navigationParentItem = null;

    protected string | Closure | null $cluster = null;

    /** @var list<class-string> | Closure | null */
    protected array | Closure | null $excludeResources = null;

    /** @var list<class-string> | Closure | null */
    protected array | Closure | null $excludePages = null;

    /** @var list<class-string> | Closure | null */
    protected array | Closure | null $excludeWidgets = null;

    /** @var list<string> | Closure | null */
    protected array | Closure | null $excludePanels = null;

    /**
     * Layout/tab settings are null until explicitly set so global config
     * survives registration and per-panel overrides can be distinguished
     * from defaults. See the *Override() getters.
     *
     * @var array<string, int> | int | Closure | null
     */
    protected array | int | Closure | null $gridColumns = null;

    /** @var array<string, int> | int | Closure | null */
    protected array | int | Closure | null $checkboxColumns = null;

    /** @var array<string, int> | int | Closure | null */
    protected array | int | Closure | null $sectionColumnSpan = null;

    /** @var array<string, int> | int | Closure | null */
    protected array | int | Closure | null $resourceCheckboxListColumns = null;

    protected bool | Closure | null $resourcesTab = null;

    protected bool | Closure | null $pagesTab = null;

    protected bool | Closure | null $widgetsTab = null;

    protected bool | Closure | null $customPermissionsTab = null;

    protected bool | Closure | null $panelsTab = null;

    protected bool | Closure $localizePermissionLabels = false;

    protected string | Closure | null $permissionCase = null;

    protected string | Closure | null $permissionSeparator = null;

    protected bool | Closure | null $scopedToTenant = null;

    protected bool | Closure | null $centralApp = null;

    public static function make(): self
    {
        // Fresh instance per call so each panel gets independent settings.
        // (The container binding stays scoped; see PluginTest.)
        return new self;
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * Resolve this plugin's instance for a panel (current panel by default).
     *
     * Returns null outside a panel context or when the panel does not
     * register this plugin. Readers prefer the instance's explicit
     * per-panel overrides and fall back to global config.
     */
    public static function resolveForPanel(?Panel $panel = null): ?static
    {
        try {
            $panel ??= Filament::getCurrentPanel();

            if ($panel === null || ! $panel->hasPlugin(self::PLUGIN_ID)) {
                return null;
            }

            $plugin = $panel->getPlugin(self::PLUGIN_ID);

            return $plugin instanceof static ? $plugin : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function getId(): string
    {
        return self::PLUGIN_ID;
    }

    public function register(Panel $panel): void
    {
        $this->panel = $panel;

        $resources = [];

        if ($this->evaluate($this->registerRoleResource)) {
            $resources[] = RoleResource::class;
        }

        if ($this->evaluate($this->registerPermissionResource)) {
            $resources[] = PermissionResource::class;
        }

        if ($this->shouldRegisterUserResource($panel)) {
            $resources[] = UserResource::class;
        }

        if ($resources !== []) {
            $panel->resources($resources);
        }

        $this->applyConfigOverrides($panel);
    }

    public function boot(Panel $panel): void
    {
        $this->panel = $panel;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resource Registration
    // ─────────────────────────────────────────────────────────────────────────

    public function roleResource(bool | Closure $condition = true): static
    {
        $this->registerRoleResource = $condition;

        return $this;
    }

    public function permissionResource(bool | Closure $condition = true): static
    {
        $this->registerPermissionResource = $condition;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Navigation
    // ─────────────────────────────────────────────────────────────────────────

    public function navigationGroup(string | Closure | null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationIcon(string | Closure | null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function activeNavigationIcon(string | Closure | null $icon): static
    {
        $this->activeNavigationIcon = $icon;

        return $this;
    }

    public function navigationLabel(string | Closure | null $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationSort(int | Closure | null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function registerNavigation(bool | Closure $condition = true): static
    {
        $this->registerNavigation = $condition;

        return $this;
    }

    public function navigationBadge(string | Closure | null $badge): static
    {
        $this->navigationBadge = $badge;

        return $this;
    }

    /**
     * @param  string | array<string> | Closure | null  $color
     */
    public function navigationBadgeColor(string | array | Closure | null $color): static
    {
        $this->navigationBadgeColor = $color;

        return $this;
    }

    public function navigationParentItem(string | Closure | null $parentItem): static
    {
        $this->navigationParentItem = $parentItem;

        return $this;
    }

    /**
     * @param  class-string | Closure | null  $cluster
     */
    public function cluster(string | Closure | null $cluster): static
    {
        $this->cluster = $cluster;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Entity Exclusions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  list<class-string> | Closure  $resources
     */
    public function excludeResources(array | Closure $resources): static
    {
        $this->excludeResources = $resources;

        return $this;
    }

    /**
     * @param  list<class-string> | Closure  $pages
     */
    public function excludePages(array | Closure $pages): static
    {
        $this->excludePages = $pages;

        return $this;
    }

    /**
     * @param  list<class-string> | Closure  $widgets
     */
    public function excludeWidgets(array | Closure $widgets): static
    {
        $this->excludeWidgets = $widgets;

        return $this;
    }

    /**
     * @param  list<string> | Closure  $panels
     */
    public function excludePanels(array | Closure $panels): static
    {
        $this->excludePanels = $panels;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UI Configuration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, int> | int | Closure  $columns
     */
    public function gridColumns(array | int | Closure $columns): static
    {
        $this->gridColumns = $columns;

        return $this;
    }

    /**
     * @param  array<string, int> | int | Closure  $columns
     */
    public function checkboxListColumns(array | int | Closure $columns): static
    {
        $this->checkboxColumns = $columns;

        return $this;
    }

    /**
     * @param  array<string, int> | int | Closure  $span
     */
    public function sectionColumnSpan(array | int | Closure $span): static
    {
        $this->sectionColumnSpan = $span;

        return $this;
    }

    /**
     * @param  array<string, int> | int | Closure  $columns
     */
    public function resourceCheckboxListColumns(array | int | Closure $columns): static
    {
        $this->resourceCheckboxListColumns = $columns;

        return $this;
    }

    public function resourcesTab(bool | Closure $condition = true): static
    {
        $this->resourcesTab = $condition;

        return $this;
    }

    public function pagesTab(bool | Closure $condition = true): static
    {
        $this->pagesTab = $condition;

        return $this;
    }

    public function widgetsTab(bool | Closure $condition = true): static
    {
        $this->widgetsTab = $condition;

        return $this;
    }

    public function customPermissionsTab(bool | Closure $condition = true): static
    {
        $this->customPermissionsTab = $condition;

        return $this;
    }

    public function panelsTab(bool | Closure $condition = true): static
    {
        $this->panelsTab = $condition;

        return $this;
    }

    /**
     * Enable localized permission labels based on configured translations.
     */
    public function localizePermissionLabels(bool | Closure $condition = true): static
    {
        $this->localizePermissionLabels = $condition;

        return $this;
    }

    /**
     * Restrict which role scopes are editable in the user resource.
     *
     * @param  'all'|'global_only'|'scoped_only' | Closure  $mode
     */
    public function userRoleScopeMode(string | Closure $mode): static
    {
        $this->userRoleScopeMode = $mode;

        return $this;
    }

    /**
     * Limit the selectable Authz scopes exposed by the role resource.
     *
     * @param  array<string, string> | Closure | null  $options
     */
    public function roleScopeOptionsUsing(array | Closure | null $options): static
    {
        $this->roleScopeOptions = $options;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Permission Configuration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Set permission key case format.
     *
     * @param  'snake'|'kebab'|'camel'|'pascal'|'upper_snake'|'lower' | Closure  $case
     */
    public function permissionCase(string | Closure $case): static
    {
        $this->permissionCase = $case;

        return $this;
    }

    public function permissionSeparator(string | Closure $separator): static
    {
        $this->permissionSeparator = $separator;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Multi-Tenancy / Panel Scoping
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Scope roles/permissions to the current tenant (team).
     */
    public function scopeToTenant(bool | Closure $condition = true): static
    {
        $this->scopedToTenant = $condition;

        return $this;
    }

    /**
     * Configure as central app for multi-tenant architectures.
     *
     * In central app mode, the RoleResource shows a team selector
     * so admins can manage roles across all tenants from a single panel.
     */
    public function centralApp(bool | Closure $condition = true): static
    {
        $this->centralApp = $condition;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // State Checks
    // ─────────────────────────────────────────────────────────────────────────

    public function isScopedToTenant(): bool
    {
        return (bool) ($this->evaluate($this->scopedToTenant) ?? true);
    }

    public function isCentralApp(): bool
    {
        return (bool) ($this->evaluate($this->centralApp) ?? false);
    }

    public function hasLocalizedPermissionLabels(): bool
    {
        return $this->evaluate($this->localizePermissionLabels);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Getters
    // ─────────────────────────────────────────────────────────────────────────

    public function getPanel(): ?Panel
    {
        return $this->panel;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->evaluate($this->navigationGroup);
    }

    public function getNavigationIcon(): ?string
    {
        return $this->evaluate($this->navigationIcon);
    }

    public function getActiveNavigationIcon(): ?string
    {
        return $this->evaluate($this->activeNavigationIcon);
    }

    public function getNavigationLabel(): ?string
    {
        return $this->evaluate($this->navigationLabel);
    }

    public function getNavigationSort(): ?int
    {
        return $this->evaluate($this->navigationSort);
    }

    public function shouldRegisterNavigation(): bool
    {
        return (bool) ($this->evaluate($this->registerNavigation) ?? true);
    }

    public function getNavigationBadge(): ?string
    {
        return $this->evaluate($this->navigationBadge);
    }

    /**
     * @return string | array<string> | null
     */
    public function getNavigationBadgeColor(): string | array | null
    {
        return $this->evaluate($this->navigationBadgeColor);
    }

    public function getNavigationParentItem(): ?string
    {
        return $this->evaluate($this->navigationParentItem);
    }

    /**
     * @return class-string | null
     */
    public function getCluster(): ?string
    {
        return $this->evaluate($this->cluster);
    }

    /**
     * @return array<string, int> | int
     */
    public function getGridColumns(): array | int
    {
        $value = $this->evaluate($this->gridColumns);

        return is_array($value) || is_int($value) ? $value : 2;
    }

    /**
     * @return array<string, int> | int | null
     */
    public function getGridColumnsOverride(): array | int | null
    {
        if ($this->gridColumns === null) {
            return null;
        }

        $value = $this->evaluate($this->gridColumns);

        return is_array($value) || is_int($value) ? $value : null;
    }

    /**
     * @return array<string, int> | int
     */
    public function getCheckboxListColumns(): array | int
    {
        $value = $this->evaluate($this->checkboxColumns);

        return is_array($value) || is_int($value) ? $value : 3;
    }

    /**
     * @return array<string, int> | int | null
     */
    public function getCheckboxListColumnsOverride(): array | int | null
    {
        if ($this->checkboxColumns === null) {
            return null;
        }

        $value = $this->evaluate($this->checkboxColumns);

        return is_array($value) || is_int($value) ? $value : null;
    }

    /**
     * @return array<string, int> | int
     */
    public function getSectionColumnSpan(): array | int
    {
        $value = $this->evaluate($this->sectionColumnSpan);

        return is_array($value) || is_int($value) ? $value : 1;
    }

    /**
     * @return array<string, int> | int | null
     */
    public function getSectionColumnSpanOverride(): array | int | null
    {
        if ($this->sectionColumnSpan === null) {
            return null;
        }

        $value = $this->evaluate($this->sectionColumnSpan);

        return is_array($value) || is_int($value) ? $value : null;
    }

    /**
     * @return array<string, int> | int
     */
    public function getResourceCheckboxListColumns(): array | int
    {
        $value = $this->evaluate($this->resourceCheckboxListColumns);

        return is_array($value) || is_int($value) ? $value : 2;
    }

    /**
     * @return array<string, int> | int | null
     */
    public function getResourceCheckboxListColumnsOverride(): array | int | null
    {
        if ($this->resourceCheckboxListColumns === null) {
            return null;
        }

        $value = $this->evaluate($this->resourceCheckboxListColumns);

        return is_array($value) || is_int($value) ? $value : null;
    }

    /**
     * @return list<class-string>
     */
    public function getExcludedResources(): array
    {
        return $this->getExcludedResourcesOverride() ?? [];
    }

    /**
     * @return list<class-string> | null
     */
    public function getExcludedResourcesOverride(): ?array
    {
        if ($this->excludeResources === null) {
            return null;
        }

        $value = $this->evaluate($this->excludeResources);

        return is_array($value) ? array_values($value) : null;
    }

    /**
     * @return list<class-string>
     */
    public function getExcludedPages(): array
    {
        return $this->getExcludedPagesOverride() ?? [];
    }

    /**
     * @return list<class-string> | null
     */
    public function getExcludedPagesOverride(): ?array
    {
        if ($this->excludePages === null) {
            return null;
        }

        $value = $this->evaluate($this->excludePages);

        return is_array($value) ? array_values($value) : null;
    }

    /**
     * @return list<class-string>
     */
    public function getExcludedWidgets(): array
    {
        return $this->getExcludedWidgetsOverride() ?? [];
    }

    /**
     * @return list<class-string> | null
     */
    public function getExcludedWidgetsOverride(): ?array
    {
        if ($this->excludeWidgets === null) {
            return null;
        }

        $value = $this->evaluate($this->excludeWidgets);

        return is_array($value) ? array_values($value) : null;
    }

    /**
     * @return list<string>
     */
    public function getExcludedPanels(): array
    {
        return $this->getExcludedPanelsOverride() ?? [];
    }

    /**
     * @return list<string> | null
     */
    public function getExcludedPanelsOverride(): ?array
    {
        if ($this->excludePanels === null) {
            return null;
        }

        $value = $this->evaluate($this->excludePanels);

        return is_array($value) ? array_values($value) : null;
    }

    public function shouldShowResourcesTab(): bool
    {
        return (bool) ($this->evaluate($this->resourcesTab) ?? true);
    }

    public function getResourcesTabOverride(): ?bool
    {
        if ($this->resourcesTab === null) {
            return null;
        }

        return (bool) $this->evaluate($this->resourcesTab);
    }

    public function shouldShowPagesTab(): bool
    {
        return (bool) ($this->evaluate($this->pagesTab) ?? true);
    }

    public function getPagesTabOverride(): ?bool
    {
        if ($this->pagesTab === null) {
            return null;
        }

        return (bool) $this->evaluate($this->pagesTab);
    }

    public function shouldShowWidgetsTab(): bool
    {
        return (bool) ($this->evaluate($this->widgetsTab) ?? true);
    }

    public function getWidgetsTabOverride(): ?bool
    {
        if ($this->widgetsTab === null) {
            return null;
        }

        return (bool) $this->evaluate($this->widgetsTab);
    }

    public function shouldShowCustomPermissionsTab(): bool
    {
        return (bool) ($this->evaluate($this->customPermissionsTab) ?? true);
    }

    public function getCustomPermissionsTabOverride(): ?bool
    {
        if ($this->customPermissionsTab === null) {
            return null;
        }

        return (bool) $this->evaluate($this->customPermissionsTab);
    }

    public function shouldShowPanelsTab(): bool
    {
        return (bool) ($this->evaluate($this->panelsTab) ?? true);
    }

    public function getPanelsTabOverride(): ?bool
    {
        if ($this->panelsTab === null) {
            return null;
        }

        return (bool) $this->evaluate($this->panelsTab);
    }

    public function getPermissionCase(): string
    {
        return $this->evaluate($this->permissionCase) ?? 'camel';
    }

    public function getPermissionSeparator(): string
    {
        return $this->evaluate($this->permissionSeparator) ?? '_';
    }

    public function getUserRoleScopeMode(): string
    {
        $mode = $this->evaluate($this->userRoleScopeMode);

        return is_string($mode) ? $mode : 'all';
    }

    /**
     * @return array<string, string> | null
     */
    public function getRoleScopeOptions(): ?array
    {
        $options = $this->evaluate($this->roleScopeOptions);

        return is_array($options) ? $options : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────────────────

    protected function applyConfigOverrides(Panel $panel): void
    {
        // Navigation settings
        if ($this->navigationGroup !== null) {
            config()->set('filament-authz.navigation.group', $this->evaluate($this->navigationGroup));
        }

        if ($this->navigationIcon !== null) {
            config()->set('filament-authz.navigation.icons.roles', $this->evaluate($this->navigationIcon));
        }

        if ($this->activeNavigationIcon !== null) {
            config()->set('filament-authz.navigation.icons.roles_active', $this->evaluate($this->activeNavigationIcon));
        }

        if ($this->navigationLabel !== null) {
            config()->set('filament-authz.navigation.label', $this->evaluate($this->navigationLabel));
        }

        if ($this->navigationSort !== null) {
            config()->set('filament-authz.navigation.sort', $this->evaluate($this->navigationSort));
        }

        if ($this->registerNavigation !== null) {
            config()->set('filament-authz.navigation.register', $this->evaluate($this->registerNavigation));
        }

        if ($this->navigationBadge !== null) {
            config()->set('filament-authz.navigation.badge', $this->evaluate($this->navigationBadge));
        }

        if ($this->navigationBadgeColor !== null) {
            config()->set('filament-authz.navigation.badge_color', $this->evaluate($this->navigationBadgeColor));
        }

        if ($this->navigationParentItem !== null) {
            config()->set('filament-authz.navigation.parent_item', $this->evaluate($this->navigationParentItem));
        }

        if ($this->cluster !== null) {
            config()->set('filament-authz.navigation.cluster', $this->evaluate($this->cluster));
        }

        // Entity exclusions, tabs, and role-editor layout are read per-panel
        // (PanelExclusions, PermissionTabFactory) and are intentionally NOT
        // written to global config: writing them here would let the
        // last-registered panel stomp every other panel's settings.

        if ($this->roleScopeOptions !== null) {
            config()->set('filament-authz.role_resource.scope_options', $this->roleScopeOptions);
        }

        // Permission configuration
        if ($this->permissionCase !== null) {
            config()->set('authz.permissions.case', $this->evaluate($this->permissionCase));
        }

        if ($this->permissionSeparator !== null) {
            config()->set('authz.permissions.separator', $this->evaluate($this->permissionSeparator));
        }

        // Multi-tenancy (scoped_to_tenant/central_app/scopes.enforce stay
        // global: the tenant guard lives in authz core and reads global
        // config, so per-panel scoping cannot be honored coherently).
        $scopedOverride = $this->scopedToTenant !== null ? (bool) $this->evaluate($this->scopedToTenant) : null;
        $centralOverride = $this->centralApp !== null ? (bool) $this->evaluate($this->centralApp) : null;

        if ($scopedOverride !== null) {
            config()->set('filament-authz.scoped_to_tenant', $scopedOverride);
        }

        if ($centralOverride !== null) {
            config()->set('filament-authz.central_app', $centralOverride);
        }

        if ($scopedOverride !== null || $centralOverride !== null) {
            $effectiveScoped = $scopedOverride ?? config('filament-authz.scoped_to_tenant', true);
            $effectiveCentral = $centralOverride ?? config('filament-authz.central_app', false);
            config()->set('authz.scopes.enforce', $effectiveScoped && ! $effectiveCentral);
        }

        if ($this->userRoleScopeMode !== null) {
            config()->set('filament-authz.user_resource.form.role_scope_mode', $this->getUserRoleScopeMode());
        }
    }

    protected function shouldRegisterUserResource(Panel $panel): bool
    {
        // Check if user resource is explicitly disabled
        if (! config('filament-authz.user_resource.enabled', true)) {
            return false;
        }

        // Always register if auto_register is disabled
        if (! config('filament-authz.user_resource.auto_register', true)) {
            return false;
        }

        // With auto_register enabled, always register the UserResource
        // The resource will be available and discoverable by Filament
        return true;
    }
}
