<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\FilamentAuthz\Concerns\ScopesAuthzTenancy;
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use AIArmada\FilamentAuthz\Models\AuthzScope;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Resources\RoleResource\Concerns\HasAuthzFormComponents;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RoleResource extends Resource
{
    use HasAuthzFormComponents;
    use ScopesAuthzTenancy;

    protected static ?string $model = null;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (config('filament-authz.central_app', false)) {
            return $query;
        }

        return static::applyTenantScope($query);
    }

    public static function getModel(): string
    {
        return config('permission.models.role', Role::class);
    }

    public static function getModelLabel(): string
    {
        return __('filament-authz::filament-authz.resource.role.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-authz::filament-authz.resource.role.plural_label');
    }

    public static function canViewAny(): bool
    {
        return static::checkAbility('role.viewAny');
    }

    public static function canCreate(): bool
    {
        return static::checkAbility('role.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::checkAbility('role.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::checkAbility('role.delete');
    }

    protected static function checkAbility(string $ability): bool
    {
        $user = Auth::user();

        if (! $user instanceof Authorizable) {
            return false;
        }

        $superAdminRole = config('filament-authz.super_admin_role');

        if (method_exists($user, 'hasRole')) {
            $registrar = app(PermissionRegistrar::class);
            $teams = $registrar->teams;
            $registrar->teams = false;

            try {
                if ((bool) call_user_func([$user, 'hasRole'], $superAdminRole)) {
                    return true;
                }
            } finally {
                $registrar->teams = $teams;
            }
        }

        return $user->can($ability);
    }

    public static function getNavigationGroup(): ?string
    {
        return static::getPlugin()?->getNavigationGroup()
            ?? config('filament-authz.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return static::getPlugin()?->getNavigationIcon()
            ?? config('filament-authz.navigation.icons.roles');
    }

    public static function getActiveNavigationIcon(): ?string
    {
        return static::getPlugin()?->getActiveNavigationIcon()
            ?? config('filament-authz.navigation.icons.roles_active');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPlugin()?->getNavigationLabel()
            ?? config('filament-authz.navigation.label')
            ?? __('filament-authz::filament-authz.navigation.roles');
    }

    public static function getNavigationSort(): ?int
    {
        return static::getPlugin()?->getNavigationSort()
            ?? config('filament-authz.navigation.sort');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getPlugin()?->getNavigationBadge()
            ?? config('filament-authz.navigation.badge');
    }

    /**
     * @return string | array<string> | null
     */
    public static function getNavigationBadgeColor(): string | array | null
    {
        return static::getPlugin()?->getNavigationBadgeColor()
            ?? config('filament-authz.navigation.badge_color');
    }

    public static function getNavigationParentItem(): ?string
    {
        return static::getPlugin()?->getNavigationParentItem()
            ?? config('filament-authz.navigation.parent_item');
    }

    public static function getCluster(): ?string
    {
        return static::getPlugin()?->getCluster()
            ?? config('filament-authz.navigation.cluster');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $shouldRegister = static::getPlugin()?->shouldRegisterNavigation()
            ?? config('filament-authz.navigation.register', true);

        return (bool) $shouldRegister && static::canViewAny();
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return (string) config('filament-authz.role_resource.slug', 'authz/roles');
    }

    public static function form(Schema $form): Schema
    {
        $guards = config('filament-authz.guards', ['web']);
        $schema = [
            Forms\Components\TextInput::make('name')
                ->label(__('filament-authz::filament-authz.form.name'))
                ->required()
                ->maxLength(255)
                ->scopedUnique(
                    model: static::getModel(),
                    column: 'name',
                    ignoreRecord: true,
                    modifyQueryUsing: function (Builder $query, Get $get): Builder {
                        $table = $query->getModel()->getTable();
                        $guards = (array) config('filament-authz.guards', ['web']);
                        $guardName = (string) ($get('guard_name') ?: ($guards[0] ?? 'web'));

                        $query->where("{$table}.guard_name", $guardName);

                        if (config('filament-authz.authz_scopes.enabled', false) && config('filament-authz.central_app', false)) {
                            $teamsKey = app(PermissionRegistrar::class)->teamsKey;
                            $scopeId = $get($teamsKey);

                            if (filled($scopeId)) {
                                $query->where("{$table}.{$teamsKey}", $scopeId);
                            } else {
                                $query->whereNull("{$table}.{$teamsKey}");
                            }
                        }

                        return $query;
                    },
                )
                ->placeholder(__('filament-authz::filament-authz.form.name_placeholder'))
                ->helperText(__('filament-authz::filament-authz.form.name_helper'))
                ->autocomplete(false),
            Forms\Components\Select::make('guard_name')
                ->label(__('filament-authz::filament-authz.form.guard_name'))
                ->options(array_combine($guards, $guards))
                ->default($guards[0] ?? 'web')
                ->required()
                ->live()
                ->helperText(__('filament-authz::filament-authz.form.guard_name_helper')),
        ];

        if (config('filament-authz.authz_scopes.enabled', false) && config('filament-authz.central_app', false)) {
            $teamsKey = app(PermissionRegistrar::class)->teamsKey;

            $schema[] = Forms\Components\Select::make($teamsKey)
                ->label(__('filament-authz::filament-authz.form.scope'))
                ->options(AuthzScope::query()->orderBy('label')->pluck('label', 'id')->all())
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText(__('filament-authz::filament-authz.form.scope_helper'));
        }

        return $form->schema([
            Section::make(__('filament-authz::filament-authz.section.role_details'))
                ->description(__('filament-authz::filament-authz.section.role_details_description'))
                ->schema($schema)
                ->columns(2),

            static::getAuthzFormComponents()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $guards = config('filament-authz.guards', ['web']);
        $filters = [
            SelectFilter::make('guard_name')
                ->label(__('filament-authz::filament-authz.filter.guard'))
                ->options(array_combine($guards, $guards))
                ->placeholder(__('filament-authz::filament-authz.filter.all_guards'))
                ->searchable(),
        ];

        if (config('filament-authz.authz_scopes.enabled', false)) {
            $teamsKey = app(PermissionRegistrar::class)->teamsKey;
            $scopeOptions = AuthzScope::query()->orderBy('label')->pluck('label', 'id')->all();

            $filters[] = SelectFilter::make($teamsKey)
                ->label(__('filament-authz::filament-authz.filter.scope'))
                ->options([
                    '__global__' => __('filament-authz::filament-authz.filter.global_scope'),
                    ...$scopeOptions,
                ])
                ->placeholder(__('filament-authz::filament-authz.filter.all_scopes'))
                ->searchable()
                ->query(function (Builder $query, array $data) use ($teamsKey): Builder {
                    $scopeValue = $data['value'] ?? null;

                    if (! filled($scopeValue)) {
                        return $query;
                    }

                    if ($scopeValue === '__global__') {
                        return $query->whereNull($teamsKey);
                    }

                    return $query->where($teamsKey, $scopeValue);
                });
        }

        $filters[] = Filter::make('has_permissions')
            ->label(__('filament-authz::filament-authz.filter.has_permissions'))
            ->query(fn (Builder $query): Builder => $query->has('permissions'))
            ->indicator(__('filament-authz::filament-authz.filter.has_permissions'));

        $columns = [
            TextColumn::make('name')
                ->label(__('filament-authz::filament-authz.table.name'))
                ->searchable()
                ->sortable()
                ->copyable(),
            TextColumn::make('guard_name')
                ->label(__('filament-authz::filament-authz.table.guard_name'))
                ->badge()
                ->sortable(),
        ];

        if (config('filament-authz.authz_scopes.enabled', false)) {
            $columns[] = TextColumn::make('authzScope.label')
                ->label(__('filament-authz::filament-authz.table.scope'))
                ->formatStateUsing(fn (?string $state): string => $state ?? __('filament-authz::filament-authz.table.global_scope'))
                ->sortable()
                ->toggleable();
        }

        $columns[] = TextColumn::make('permissions_count')
            ->counts('permissions')
            ->badge()
            ->color('primary')
            ->label(__('filament-authz::filament-authz.table.permissions_count'))
            ->sortable();

        $columns[] = TextColumn::make('created_at')
            ->label(__('filament-authz::filament-authz.table.created_at'))
            ->since()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        $columns[] = TextColumn::make('updated_at')
            ->label(__('filament-authz::filament-authz.table.updated_at'))
            ->since()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        return $table->columns($columns)->filters($filters)->actions([
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ])->bulkActions([
            Actions\DeleteBulkAction::make(),
        ])->defaultSort('name')
            ->striped()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->deferFilters()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('filament-authz::filament-authz.empty_state.heading'))
            ->emptyStateDescription(__('filament-authz::filament-authz.empty_state.description'))
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

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
