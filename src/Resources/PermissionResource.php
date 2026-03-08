<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\FilamentAuthz\Concerns\ScopesAuthzTenancy;
use AIArmada\FilamentAuthz\Models\AuthzScope;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Spatie\Permission\PermissionRegistrar;

class PermissionResource extends Resource
{
    use ScopesAuthzTenancy;

    protected static ?string $model = null;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        // Permissions are global in Spatie's teams implementation
        // Only roles are scoped by team_id
        return parent::getEloquentQuery();
    }

    public static function getModel(): string
    {
        return config('permission.models.permission', Permission::class);
    }

    public static function canViewAny(): bool
    {
        return static::checkAbility('permission.viewAny');
    }

    public static function canCreate(): bool
    {
        return static::checkAbility('permission.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::checkAbility('permission.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::checkAbility('permission.delete');
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
        return config('filament-authz.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return config('filament-authz.navigation.icons.permissions');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-authz.navigation.sort');

        return is_int($sort) ? $sort + 1 : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-authz.navigation.register', true) && static::canViewAny();
    }

    public static function form(Schema $form): Schema
    {
        $guards = config('filament-authz.guards', ['web']);

        return $form->schema([
            Section::make('Permission Details')
                ->description('Create a permission name and guard.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->placeholder('e.g. orders.viewAny')
                        ->helperText('Names should follow your permission naming convention.')
                        ->autocomplete(false),
                    Forms\Components\Select::make('guard_name')
                        ->options(array_combine($guards, $guards))
                        ->default($guards[0] ?? 'web')
                        ->required()
                        ->preload()
                        ->helperText('Guards map to auth drivers (web, api, etc.).'),
                ])->columns(2),
            Section::make('Assignment Overview')
                ->description('Shows where this permission is currently assigned.')
                ->visible(fn (?Model $record): bool => $record !== null)
                ->schema([
                    Placeholder::make('assignment_summary')
                        ->label('Summary')
                        ->content(fn (?Model $record): string => static::getAssignmentSummaryText($record)),
                    Placeholder::make('assigned_roles')
                        ->label('Assigned Roles')
                        ->content(fn (?Model $record): Htmlable | string => static::renderAssignedRoles($record)),
                    Placeholder::make('direct_users')
                        ->label('Users With Direct Permission')
                        ->content(fn (?Model $record): Htmlable | string => static::renderDirectUsers($record)),
                ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        $guards = config('filament-authz.guards', ['web']);

        return $table->columns([
            TextColumn::make('name')->searchable()->sortable()->copyable(),
            TextColumn::make('guard_name')->badge()->sortable(),
            TextColumn::make('created_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('guard_name')
                ->label('Guard')
                ->options(array_combine($guards, $guards))
                ->placeholder('All guards')
                ->searchable(),
            Filter::make('assigned')
                ->label('Assigned to roles')
                ->query(fn (Builder $query): Builder => $query->has('roles'))
                ->indicator('Assigned'),
        ])->actions([
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
            ->emptyStateHeading('No permissions yet')
            ->emptyStateDescription('Create permissions to grant access across the panel.')
            ->emptyStateIcon('heroicon-o-key');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }

    protected static function getAssignmentSummaryText(?Model $record): string
    {
        if ($record === null) {
            return '-';
        }

        $roleCount = method_exists($record, 'roles')
            ? $record->roles()->count()
            : 0;

        $directUserCount = method_exists($record, 'users')
            ? $record->users()->count()
            : 0;

        return "Assigned to {$roleCount} role(s), and {$directUserCount} user(s) directly.";
    }

    protected static function renderAssignedRoles(?Model $record): Htmlable | string
    {
        if ($record === null || ! method_exists($record, 'roles')) {
            return '-';
        }

        $registrar = app(PermissionRegistrar::class);
        $teamsEnabled = (bool) $registrar->teams;
        $teamsKey = $registrar->teamsKey;

        $columns = ['id', 'name'];

        if ($teamsEnabled) {
            $columns[] = $teamsKey;
        }

        /** @var Collection<int, Model> $roles */
        $roles = $record->roles()
            ->orderBy('name')
            ->limit(20)
            ->get($columns);

        $total = $record->roles()->count();
        $scopeLabels = static::resolveScopeLabels($roles, $teamsKey, $teamsEnabled);

        $items = $roles
            ->map(function (Model $role) use ($teamsEnabled, $teamsKey, $scopeLabels): string {
                $name = (string) $role->getAttribute('name');
                $scopeId = $teamsEnabled ? $role->getAttribute($teamsKey) : null;

                return static::formatRoleLabel($name, $scopeId, $scopeLabels, $teamsEnabled);
            })
            ->all();

        return static::renderBulletedList($items, $total);
    }

    protected static function renderDirectUsers(?Model $record): Htmlable | string
    {
        if ($record === null || ! method_exists($record, 'users')) {
            return '-';
        }

        /** @var Collection<int, Model> $users */
        $users = $record->users()
            ->limit(20)
            ->get();

        $total = $record->users()->count();

        $items = $users
            ->map(static function (Model $user): string {
                $name = mb_trim((string) $user->getAttribute('name'));
                $email = mb_trim((string) $user->getAttribute('email'));

                if ($name !== '' && $email !== '') {
                    return "{$name} <{$email}>";
                }

                if ($name !== '') {
                    return $name;
                }

                if ($email !== '') {
                    return $email;
                }

                return (string) $user->getKey();
            })
            ->all();

        return static::renderBulletedList($items, $total);
    }

    /**
     * @param  list<string>  $items
     */
    protected static function renderBulletedList(array $items, int $total): Htmlable | string
    {
        if ($total === 0) {
            return '-';
        }

        $list = implode('', array_map(static fn (string $item): string => '<li>' . e($item) . '</li>', $items));
        $remaining = max($total - count($items), 0);
        $suffix = $remaining > 0 ? '<p>+' . e((string) $remaining) . ' more</p>' : '';

        return new HtmlString('<ul class="list-disc list-inside space-y-1">' . $list . '</ul>' . $suffix);
    }

    /**
     * @param  Collection<int, Model>  $roles
     * @return array<string, string>
     */
    protected static function resolveScopeLabels(Collection $roles, string $teamsKey, bool $teamsEnabled): array
    {
        if (! $teamsEnabled || ! config('filament-authz.authz_scopes.enabled', false)) {
            return [];
        }

        $scopeIds = $roles
            ->pluck($teamsKey)
            ->filter(static fn (mixed $scopeId): bool => is_scalar($scopeId) && (string) $scopeId !== '')
            ->map(static fn (mixed $scopeId): string => (string) $scopeId)
            ->unique()
            ->values()
            ->all();

        if ($scopeIds === []) {
            return [];
        }

        return AuthzScope::query()
            ->whereIn('id', $scopeIds)
            ->pluck('label', 'id')
            ->mapWithKeys(static fn (mixed $label, mixed $id): array => [(string) $id => (string) $label])
            ->all();
    }

    /**
     * @param  array<string, string>  $scopeLabels
     */
    protected static function formatRoleLabel(string $name, mixed $scopeId, array $scopeLabels, bool $teamsEnabled): string
    {
        if (! $teamsEnabled || ! config('filament-authz.authz_scopes.enabled', false)) {
            return $name;
        }

        if (! is_scalar($scopeId) || (string) $scopeId === '') {
            return "{$name} (Global)";
        }

        $scopeLabel = $scopeLabels[(string) $scopeId] ?? null;

        if (! is_string($scopeLabel) || $scopeLabel === '') {
            return "{$name} (Scoped)";
        }

        return "{$name} ({$scopeLabel})";
    }
}
