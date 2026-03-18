<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages;
use AIArmada\FilamentAuthz\Support\UserAuthzForm;
use AIArmada\FilamentAuthz\Tables\Actions\ImpersonateTableAction;
use Filament\Actions;
use Filament\Forms;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserResource extends Resource
{
    protected static ?string $model = null;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModel(): string
    {
        $model = config('filament-authz.user_resource.model');

        if (is_string($model) && $model !== '') {
            return $model;
        }

        $guard = config('filament-authz.guards.0', 'web');
        $provider = config("auth.guards.{$guard}.provider");

        return (string) config("auth.providers.{$provider}.model", 'App\\Models\\User');
    }

    public static function canViewAny(): bool
    {
        return static::checkAbility('viewAny');
    }

    public static function canView(Model $record): bool
    {
        return static::checkAbility('view');
    }

    public static function canCreate(): bool
    {
        return static::checkAbility('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::checkAbility('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::checkAbility('delete');
    }

    protected static function checkAbility(string $action): bool
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

        $ability = Authz::buildPermissionKey('User', $action);

        return $user->can($ability);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.user_resource.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return config('filament-authz.user_resource.navigation.icon');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-authz.user_resource.navigation.sort');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return (string) config('filament-authz.user_resource.slug', 'authz/users');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('User Details')
                ->schema(static::getDefaultFormFields())
                ->columns(2),
            ...UserAuthzForm::components(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ImpersonateTableAction::make(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('name')
            ->striped()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->deferFilters()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription('Create users and assign roles or permissions.')
            ->emptyStateIcon('heroicon-o-user-group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<Forms\Components\TextInput>
     */
    protected static function getDefaultFormFields(): array
    {
        $fields = (array) config('filament-authz.user_resource.form.fields', ['name', 'email', 'password']);
        $components = [];

        foreach ($fields as $field) {
            $components[] = match ($field) {
                'name' => Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                'email' => Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                'password' => Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(function (?string $state): ?string {
                        if ($state === null || $state === '') {
                            return null;
                        }

                        return Hash::make($state);
                    })
                    ->dehydrated(function (?string $state): bool {
                        return filled($state);
                    })
                    ->required(function (string $operation): bool {
                        return $operation === 'create';
                    })
                    ->maxLength(255),
                default => Forms\Components\TextInput::make((string) $field)
                    ->maxLength(255),
            };
        }

        return $components;
    }
}
