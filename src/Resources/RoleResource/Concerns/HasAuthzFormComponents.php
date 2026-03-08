<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Concerns;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Form components for permission management with tabs.
 *
 * Features:
 * - Tabbed interface (Resources/Pages/Widgets/Custom)
 * - Configurable responsive grid layout
 * - Searchable resource/page/widget sections
 * - Grouped by package with collapsible sections
 * - Bulk toggle links for permissions
 * - Badge counts on tabs
 * - Full i18n support
 */
trait HasAuthzFormComponents
{
    /**
     * Get all permission tabs for the role form.
     *
     * @return array<Tab>
     */
    public static function getPermissionTabs(): array
    {
        $tabs = [];
        $plugin = static::getPlugin();

        if ($plugin?->shouldShowResourcesTab() ?? config('filament-authz.role_resource.tabs.resources', true)) {
            $tabs[] = static::getResourcesTab();
        }

        if ($plugin?->shouldShowPagesTab() ?? config('filament-authz.role_resource.tabs.pages', true)) {
            $tabs[] = static::getPagesTab();
        }

        if ($plugin?->shouldShowWidgetsTab() ?? config('filament-authz.role_resource.tabs.widgets', true)) {
            $tabs[] = static::getWidgetsTab();
        }

        if ($plugin?->shouldShowCustomPermissionsTab() ?? config('filament-authz.role_resource.tabs.custom_permissions', true)) {
            $tabs[] = static::getCustomPermissionsTab();
        }

        if (config('filament-authz.role_resource.tabs.direct_permissions', true)) {
            $tabs[] = static::getDirectPermissionsTab();
        }

        return $tabs;
    }

    /**
     * Get the full Tabs component with all permission tabs.
     */
    public static function getAuthzFormComponents(): Tabs
    {
        return Tabs::make('Permissions')
            ->contained()
            ->persistTabInQueryString()
            ->tabs(static::getPermissionTabs());
    }

    protected static function getResourcesTab(): Tab
    {
        $resources = Authz::getResources();
        $count = $resources->sum(fn (array $r): int => count($r['permissions']));
        $plugin = static::getPlugin();

        $grouped = static::groupByPackage($resources);

        return Tab::make('resources')
            ->label(__('filament-authz::filament-authz.tabs.resources'))
            ->icon('heroicon-o-cube')
            ->badge($count)
            ->visible($resources->isNotEmpty())
            ->schema([
                TextInput::make('resource_search')
                    ->label(__('filament-authz::filament-authz.search.resources'))
                    ->placeholder(__('filament-authz::filament-authz.search.resources_placeholder'))
                    ->prefixIcon('heroicon-o-magnifying-glass')
                    ->suffixAction(
                        Action::make('clearResourceSearch')
                            ->label(__('filament-authz::filament-authz.search.clear_resources'))
                            ->icon('heroicon-o-x-mark')
                            ->actionJs("\$set('resource_search', '')")
                    )
                    ->autocomplete(false)
                    ->dehydrated(false),
                ...static::getGroupedResourceSections($grouped, $plugin),
            ]);
    }

    /**
     * Get grouped resource sections by package.
     *
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $grouped
     * @return array<Section>
     */
    protected static function getGroupedResourceSections(Collection $grouped, ?FilamentAuthzPlugin $plugin): array
    {
        $sections = [];

        foreach ($grouped as $packageName => $resources) {
            $permissionCount = $resources->sum(fn (array $r): int => count($r['permissions']));
            $resourceCount = $resources->count();
            $searchableLabels = $resources->map(fn (array $r): string => Str::lower(static::normalizeLabel($r['label'])))->values()->all();
            $searchTerms = implode('|', $searchableLabels);

            $sections[] = Section::make($packageName)
                ->description(trans_choice('filament-authz::filament-authz.section.resources_count', $resourceCount, ['count' => $resourceCount]))
                ->icon('heroicon-o-cube-transparent')
                ->collapsible()
                ->collapsed()
                ->visibleJs("!\$get('resource_search')?.trim() || '{$searchTerms}'.includes(\$get('resource_search').toLowerCase().trim())")
                ->schema([
                    Grid::make()
                        ->schema(
                            $resources
                                ->sortBy('label')
                                ->map(fn (array $resource): Section => static::getResourceSection($resource))
                                ->values()
                                ->all()
                        )
                        ->columns($plugin?->getGridColumns() ?? config('filament-authz.role_resource.grid_columns', 2)),
                ]);
        }

        return $sections;
    }

    /**
     * @param  array{class: class-string, permissions: array<string, string>, label: string}  $resource
     */
    protected static function getResourceSection(array $resource): Section
    {
        $permissions = $resource['permissions'];
        $plugin = static::getPlugin();
        $label = $resource['label'];
        $displayLabel = static::normalizeLabel($label);

        $checkboxColumns = $plugin?->getResourceCheckboxListColumns()
            ?? $plugin?->getCheckboxListColumns()
            ?? config('filament-authz.role_resource.checkbox_columns', 3);

        $sectionColumnSpan = $plugin?->getSectionColumnSpan()
            ?? config('filament-authz.role_resource.section_column_span', 1);

        $lowerLabel = Str::lower($displayLabel);
        $safeKey = 'permissions_resource_' . md5($resource['class']);

        return Section::make($displayLabel)
            ->icon('heroicon-o-rectangle-stack')
            ->collapsible()
            ->compact()
            ->columnSpan($sectionColumnSpan)
            ->visibleJs("!\$get('resource_search')?.trim() || '{$lowerLabel}'.includes(\$get('resource_search').toLowerCase().trim())")
            ->schema([
                CheckboxList::make($safeKey)
                    ->label('')
                    ->hiddenLabel()
                    ->options(static::localizePermissions($permissions))
                    ->columns($checkboxColumns)
                    ->bulkToggleable()
                    ->gridDirection('row')
                    ->default([])
                    ->afterStateHydrated(fn (CheckboxList $component, ?Model $record) => static::setPermissionStateForRecord($component, $record)),
            ]);
    }

    protected static function getPagesTab(): Tab
    {
        $pages = Authz::getPages();
        $plugin = static::getPlugin();

        $checkboxColumns = $plugin?->getCheckboxListColumns()
            ?? config('filament-authz.role_resource.checkbox_columns', 3);

        $grouped = static::groupByPackage($pages);

        return Tab::make('pages')
            ->label(__('filament-authz::filament-authz.tabs.pages'))
            ->icon('heroicon-o-document-text')
            ->badge($pages->count())
            ->visible($pages->isNotEmpty())
            ->schema([
                TextInput::make('page_search')
                    ->label(__('filament-authz::filament-authz.search.pages'))
                    ->placeholder(__('filament-authz::filament-authz.search.pages_placeholder'))
                    ->prefixIcon('heroicon-o-magnifying-glass')
                    ->suffixAction(
                        Action::make('clearPageSearch')
                            ->label(__('filament-authz::filament-authz.search.clear_pages'))
                            ->icon('heroicon-o-x-mark')
                            ->actionJs("\$set('page_search', '')")
                    )
                    ->autocomplete(false)
                    ->dehydrated(false),
                ...static::getGroupedPageSections($grouped, $checkboxColumns),
            ]);
    }

    /**
     * Get grouped page sections by package.
     *
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $grouped
     * @return array<Section>
     */
    protected static function getGroupedPageSections(Collection $grouped, int $checkboxColumns): array
    {
        $sections = [];

        foreach ($grouped as $packageName => $pages) {
            $searchTerms = $pages->pluck('label')->map(fn (string $l): string => Str::lower($l))->implode('|');
            $safeKey = 'permissions_pages_' . md5($packageName);

            $sections[] = Section::make($packageName)
                ->description(trans_choice('filament-authz::filament-authz.section.pages_count', $pages->count(), ['count' => $pages->count()]))
                ->icon('heroicon-o-folder')
                ->collapsible()
                ->collapsed()
                ->visibleJs("!\$get('page_search')?.trim() || '{$searchTerms}'.includes(\$get('page_search').toLowerCase().trim())")
                ->schema([
                    CheckboxList::make($safeKey)
                        ->label('')
                        ->hiddenLabel()
                        ->options(
                            $pages
                                ->sortBy('label')
                                ->mapWithKeys(fn (array $p): array => [$p['permission'] => $p['label']])
                                ->all()
                        )
                        ->columns($checkboxColumns)
                        ->bulkToggleable()
                        ->gridDirection('row')
                        ->default([])
                        ->afterStateHydrated(fn (CheckboxList $component, ?Model $record) => static::setPermissionStateForRecord($component, $record)),
                ]);
        }

        return $sections;
    }

    protected static function getWidgetsTab(): Tab
    {
        $widgets = Authz::getWidgets();
        $plugin = static::getPlugin();

        $checkboxColumns = $plugin?->getCheckboxListColumns()
            ?? config('filament-authz.role_resource.checkbox_columns', 3);

        $grouped = static::groupByPackage($widgets);

        return Tab::make('widgets')
            ->label(__('filament-authz::filament-authz.tabs.widgets'))
            ->icon('heroicon-o-squares-2x2')
            ->badge($widgets->count())
            ->visible($widgets->isNotEmpty())
            ->schema([
                TextInput::make('widget_search')
                    ->label(__('filament-authz::filament-authz.search.widgets'))
                    ->placeholder(__('filament-authz::filament-authz.search.widgets_placeholder'))
                    ->prefixIcon('heroicon-o-magnifying-glass')
                    ->suffixAction(
                        Action::make('clearWidgetSearch')
                            ->label(__('filament-authz::filament-authz.search.clear_widgets'))
                            ->icon('heroicon-o-x-mark')
                            ->actionJs("\$set('widget_search', '')")
                    )
                    ->autocomplete(false)
                    ->dehydrated(false),
                ...static::getGroupedWidgetSections($grouped, $checkboxColumns),
            ]);
    }

    /**
     * Get grouped widget sections by package.
     *
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $grouped
     * @return array<Section>
     */
    protected static function getGroupedWidgetSections(Collection $grouped, int $checkboxColumns): array
    {
        $sections = [];

        foreach ($grouped as $packageName => $widgets) {
            $searchTerms = $widgets->pluck('label')->map(fn (string $l): string => Str::lower($l))->implode('|');
            $safeKey = 'permissions_widgets_' . md5($packageName);

            $sections[] = Section::make($packageName)
                ->description(trans_choice('filament-authz::filament-authz.section.widgets_count', $widgets->count(), ['count' => $widgets->count()]))
                ->icon('heroicon-o-square-3-stack-3d')
                ->collapsible()
                ->collapsed()
                ->visibleJs("!\$get('widget_search')?.trim() || '{$searchTerms}'.includes(\$get('widget_search').toLowerCase().trim())")
                ->schema([
                    CheckboxList::make($safeKey)
                        ->label('')
                        ->hiddenLabel()
                        ->options(
                            $widgets
                                ->sortBy('label')
                                ->mapWithKeys(fn (array $w): array => [$w['permission'] => $w['label']])
                                ->all()
                        )
                        ->columns($checkboxColumns)
                        ->bulkToggleable()
                        ->gridDirection('row')
                        ->default([])
                        ->afterStateHydrated(fn (CheckboxList $component, ?Model $record) => static::setPermissionStateForRecord($component, $record)),
                ]);
        }

        return $sections;
    }

    protected static function getCustomPermissionsTab(): Tab
    {
        $custom = Authz::getCustomPermissions();
        $plugin = static::getPlugin();

        $checkboxColumns = $plugin?->getCheckboxListColumns()
            ?? config('filament-authz.role_resource.checkbox_columns', 3);

        return Tab::make('custom')
            ->label(__('filament-authz::filament-authz.tabs.custom'))
            ->icon('heroicon-o-cog-6-tooth')
            ->badge(count($custom))
            ->visible(! empty($custom))
            ->schema([
                Section::make(__('filament-authz::filament-authz.section.custom'))
                    ->description(__('filament-authz::filament-authz.section.custom_description'))
                    ->collapsible()
                    ->schema([
                        CheckboxList::make('permissions_custom')
                            ->label('')
                            ->options(static::localizePermissions($custom))
                            ->columns($checkboxColumns)
                            ->bulkToggleable()
                            ->searchable()
                            ->gridDirection('row')
                            ->default([])
                            ->afterStateHydrated(fn (CheckboxList $component, ?Model $record) => static::setPermissionStateForRecord($component, $record)),
                    ]),
            ]);
    }

    protected static function getDirectPermissionsTab(): Tab
    {
        return Tab::make('direct_permissions')
            ->label(__('filament-authz::filament-authz.tabs.direct_permissions'))
            ->icon('heroicon-o-key')
            ->schema([
                Section::make(__('filament-authz::filament-authz.section.direct_permissions'))
                    ->description(__('filament-authz::filament-authz.section.direct_permissions_description'))
                    ->schema([
                        CheckboxList::make('permissions_direct')
                            ->label(__('filament-authz::filament-authz.form.direct_permissions'))
                            ->helperText(__('filament-authz::filament-authz.form.direct_permissions_helper'))
                            ->options(fn (Get $get): array => static::getDirectPermissionOptions($get('guard_name')))
                            ->searchable()
                            ->columns(3)
                            ->bulkToggleable()
                            ->gridDirection('row')
                            ->default([])
                            ->afterStateHydrated(fn (CheckboxList $component, ?Model $record) => static::setPermissionStateForRecord($component, $record)),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function getDirectPermissionOptions(mixed $guard): array
    {
        $guards = (array) config('filament-authz.guards', ['web']);
        $guardName = is_string($guard) && $guard !== '' ? $guard : ($guards[0] ?? 'web');

        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Model> $permissionClass */
        $permissionClass = $registrar->getPermissionClass();

        $names = $permissionClass::query()
            ->where('guard_name', $guardName)
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        $directNames = array_values(array_diff($names, static::getDiscoveredPermissionNames()));

        if ($directNames === []) {
            return [];
        }

        $options = array_combine($directNames, $directNames);

        return static::localizePermissions($options);
    }

    /**
     * @return list<string>
     */
    protected static function getDiscoveredPermissionNames(): array
    {
        /** @var list<string> $resourcePermissions */
        $resourcePermissions = Authz::getResources()
            ->flatMap(static fn (array $resource): array => array_keys($resource['permissions'] ?? []))
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        /** @var list<string> $pagePermissions */
        $pagePermissions = Authz::getPages()
            ->pluck('permission')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        /** @var list<string> $widgetPermissions */
        $widgetPermissions = Authz::getWidgets()
            ->pluck('permission')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        /** @var list<string> $customPermissions */
        $customPermissions = array_map('strval', array_keys(Authz::getCustomPermissions()));

        return Collection::make([
            ...$resourcePermissions,
            ...$pagePermissions,
            ...$widgetPermissions,
            ...$customPermissions,
        ])->filter(static fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Set permission state from record for edit/view operations.
     *
     * Filters to only include permissions that are valid options for this specific CheckboxList.
     */
    public static function setPermissionStateForRecord(
        CheckboxList $component,
        ?Model $record
    ): void {
        if ($record === null || ! method_exists($record, 'permissions')) {
            return;
        }

        $permissionNames = $record->permissions()->pluck('name')->toArray();
        $validOptions = array_keys($component->getOptions());
        $filteredPermissions = array_values(array_intersect($permissionNames, $validOptions));
        $component->state($filteredPermissions);
    }

    /**
     * Localize permission labels if configured.
     *
     * @param  array<string, string>  $permissions
     * @return array<string, string>
     */
    protected static function localizePermissions(array $permissions): array
    {
        $plugin = static::getPlugin();

        if (! $plugin?->hasLocalizedPermissionLabels()) {
            return $permissions;
        }

        $localized = [];

        foreach ($permissions as $key => $label) {
            $translationKey = 'filament-authz::filament-authz.permissions.' . str_replace(['_', '.'], '_', $key);

            if (trans()->has($translationKey)) {
                $localized[$key] = __($translationKey);
            } else {
                $localized[$key] = $label;
            }
        }

        return $localized;
    }

    /**
     * Normalize labels for consistent display.
     */
    protected static function normalizeLabel(string $label): string
    {
        return Str::headline($label);
    }

    /**
     * Group items by their package namespace.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    protected static function groupByPackage(Collection $items): Collection
    {
        return $items
            ->groupBy(fn (array $item): string => static::extractPackageName($item['class']))
            ->sortKeys();
    }

    /**
     * Extract a friendly package name from a class namespace.
     */
    protected static function extractPackageName(string $class): string
    {
        $namespace = Str::beforeLast($class, '\\');

        $parts = explode('\\', $namespace);

        if (count($parts) >= 2) {
            $vendor = $parts[0];
            $package = $parts[1];

            if (Str::startsWith($package, 'Filament')) {
                $package = Str::after($package, 'Filament');
            }

            return Str::headline($package) ?: Str::headline($vendor);
        }

        return Str::headline($parts[0] ?? 'Other');
    }

    /**
     * Get the FilamentAuthz plugin instance.
     */
    protected static function getPlugin(): ?FilamentAuthzPlugin
    {
        try {
            $panel = Filament::getCurrentOrDefaultPanel();

            if ($panel === null) {
                return null;
            }

            /** @var FilamentAuthzPlugin|null */
            return $panel->getPlugin('filament-authz');
        } catch (Throwable) {
            return null;
        }
    }
}
