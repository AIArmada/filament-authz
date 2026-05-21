<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\UserResource;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

/**
 * Filament Authz Plugin with comprehensive fluent API.
 *
 * Features:
 * - Multi-panel support with per-panel configuration
 * - Tenant-scoped permissions (optional)
 * - Central app mode for multi-tenant architectures
 * - Customizable layout (grid columns, checkbox columns, section spans)
 * - Simple/detailed resource permission views
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

    protected bool | Closure $registerNavigation = true;

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

    /** @var array<string, int> | int | Closure */
    protected array | int | Closure $gridColumns = 2;

    /** @var array<string, int> | int | Closure */
    protected array | int | Closure $checkboxColumns = 3;

    /** @var array<string, int> | int | Closure */
    protected array | int | Closure $sectionColumnSpan = 1;

    /** @var array<string, int> | int | Closure */
    protected array | int | Closure $resourceCheckboxListColumns = 2;

    protected bool | Closure $resourcesTab = true;

    protected bool | Closure $pagesTab = true;

    protected bool | Closure $widgetsTab = true;

    protected bool | Closure $customPermissionsTab = true;

    protected bool | Closure $simpleResourcePermissionView = false;

    protected bool | Closure $localizePermissionLabels = false;

    protected string | Closure | null $permissionCase = null;

    protected string | Closure | null $permissionSeparator = null;

    protected bool | Closure $scopedToTenant = true;

    protected bool | Closure $centralApp = false;

    protected string | Closure | null $tenantOwnershipRelationship = null;

    protected string | Closure | null $tenantRelationshipName = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
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

        $this->registerImpersonationBanner();
    }

    protected function registerImpersonationBanner(): void
    {
        if (! config('filament-authz.impersonate.enabled', true)) {
            return;
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_START,
            fn (): string => Blade::render('@include("filament-authz::components.impersonation-banner")'),
        );
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

    /**
     * Enable simple flat view for resource permissions instead of grouped sections.
     */
    public function simpleResourcePermissionView(bool | Closure $condition = true): static
    {
        $this->simpleResourcePermissionView = $condition;

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

    /**
     * Set the tenant relationship name on the user model.
     */
    public function tenantRelationshipName(string | Closure | null $name): static
    {
        $this->tenantRelationshipName = $name;

        return $this;
    }

    /**
     * Set the tenant ownership relationship name on the role model.
     */
    public function tenantOwnershipRelationshipName(string | Closure | null $name): static
    {
        $this->tenantOwnershipRelationship = $name;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // State Checks
    // ─────────────────────────────────────────────────────────────────────────

    public function isScopedToTenant(): bool
    {
        return $this->evaluate($this->scopedToTenant);
    }

    public function isCentralApp(): bool
    {
        return $this->evaluate($this->centralApp);
    }

    public function hasSimpleResourcePermissionView(): bool
    {
        return $this->evaluate($this->simpleResourcePermissionView);
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
        return $this->evaluate($this->registerNavigation);
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
        return $this->evaluate($this->gridColumns);
    }

    /**
     * @return array<string, int> | int
     */
    public function getCheckboxListColumns(): array | int
    {
        return $this->evaluate($this->checkboxColumns);
    }

    /**
     * @return array<string, int> | int
     */
    public function getSectionColumnSpan(): array | int
    {
        return $this->evaluate($this->sectionColumnSpan);
    }

    /**
     * @return array<string, int> | int
     */
    public function getResourceCheckboxListColumns(): array | int
    {
        return $this->evaluate($this->resourceCheckboxListColumns);
    }

    public function getTenantOwnershipRelationshipName(): ?string
    {
        return $this->evaluate($this->tenantOwnershipRelationship);
    }

    public function getTenantRelationshipName(): ?string
    {
        return $this->evaluate($this->tenantRelationshipName);
    }

    /**
     * @return list<class-string>
     */
    public function getExcludedResources(): array
    {
        return $this->evaluate($this->excludeResources) ?? [];
    }

    /**
     * @return list<class-string>
     */
    public function getExcludedPages(): array
    {
        return $this->evaluate($this->excludePages) ?? [];
    }

    /**
     * @return list<class-string>
     */
    public function getExcludedWidgets(): array
    {
        return $this->evaluate($this->excludeWidgets) ?? [];
    }

    public function shouldShowResourcesTab(): bool
    {
        return $this->evaluate($this->resourcesTab);
    }

    public function shouldShowPagesTab(): bool
    {
        return $this->evaluate($this->pagesTab);
    }

    public function shouldShowWidgetsTab(): bool
    {
        return $this->evaluate($this->widgetsTab);
    }

    public function shouldShowCustomPermissionsTab(): bool
    {
        return $this->evaluate($this->customPermissionsTab);
    }

    public function getPermissionCase(): string
    {
        return $this->evaluate($this->permissionCase) ?? 'snake';
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

        config()->set('filament-authz.navigation.register', $this->evaluate($this->registerNavigation));

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

        // Entity exclusions
        if ($this->excludeResources !== null) {
            config()->set('filament-authz.resources.exclude', $this->evaluate($this->excludeResources));
        }

        if ($this->excludePages !== null) {
            config()->set('filament-authz.pages.exclude', $this->evaluate($this->excludePages));
        }

        if ($this->excludeWidgets !== null) {
            config()->set('filament-authz.widgets.exclude', $this->evaluate($this->excludeWidgets));
        }

        // UI configuration
        config()->set('filament-authz.role_resource.grid_columns', $this->evaluate($this->gridColumns));
        config()->set('filament-authz.role_resource.checkbox_columns', $this->evaluate($this->checkboxColumns));
        config()->set('filament-authz.role_resource.section_column_span', $this->evaluate($this->sectionColumnSpan));

        if ($this->roleScopeOptions !== null) {
            config()->set('filament-authz.role_resource.scope_options', $this->roleScopeOptions);
        }

        // Tabs
        config()->set('filament-authz.role_resource.tabs.resources', $this->evaluate($this->resourcesTab));
        config()->set('filament-authz.role_resource.tabs.pages', $this->evaluate($this->pagesTab));
        config()->set('filament-authz.role_resource.tabs.widgets', $this->evaluate($this->widgetsTab));
        config()->set('filament-authz.role_resource.tabs.custom_permissions', $this->evaluate($this->customPermissionsTab));

        // View modes
        config()->set('filament-authz.role_resource.simple_resource_permission_view', $this->evaluate($this->simpleResourcePermissionView));
        config()->set('filament-authz.role_resource.localize_permission_labels', $this->evaluate($this->localizePermissionLabels));

        // Permission configuration
        if ($this->permissionCase !== null) {
            config()->set('filament-authz.permissions.case', $this->evaluate($this->permissionCase));
        }

        if ($this->permissionSeparator !== null) {
            config()->set('filament-authz.permissions.separator', $this->evaluate($this->permissionSeparator));
        }

        // Multi-tenancy
        config()->set('filament-authz.scoped_to_tenant', $this->evaluate($this->scopedToTenant));
        config()->set('filament-authz.central_app', $this->evaluate($this->centralApp));

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
