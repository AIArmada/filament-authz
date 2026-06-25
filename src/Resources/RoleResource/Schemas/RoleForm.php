<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Schemas;

use AIArmada\CommerceSupport\Models\AuthzScope;
use Closure;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;

final class RoleForm
{
    /**
     * @param  class-string  $modelClass
     */
    public static function configure(Schema $form, string $modelClass, callable $getAuthzFormComponents): Schema
    {
        $guards = config('filament-authz.guards', ['web']);
        $schema = [
            Forms\Components\TextInput::make('name')
                ->label(__('filament-authz::filament-authz.form.name'))
                ->required()
                ->maxLength(255)
                ->scopedUnique(
                    model: $modelClass,
                    column: 'name',
                    ignoreRecord: true,
                    modifyQueryUsing: function (Builder $query, Get $get): Builder {
                        $table = $query->getModel()->getTable();
                        $guards = (array) config('filament-authz.guards', ['web']);
                        $guardName = (string) ($get('guard_name') ?: ($guards[0] ?? 'web'));

                        $query->where("{$table}.guard_name", $guardName);

                        if (config('authz.scopes.enabled', false) && config('filament-authz.central_app', false)) {
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

        if (config('authz.scopes.enabled', false) && config('filament-authz.central_app', false)) {
            $teamsKey = app(PermissionRegistrar::class)->teamsKey;

            $schema[] = Forms\Components\Select::make($teamsKey)
                ->label(__('filament-authz::filament-authz.form.scope'))
                ->options(static fn (): array => self::getScopeOptions())
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

            $getAuthzFormComponents()
                ->columnSpanFull(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function getScopeOptions(): array
    {
        $configured = self::getConfiguredScopeOptions();

        if ($configured !== null) {
            return $configured;
        }

        return AuthzScope::query()
            ->orderBy('label')
            ->pluck('label', 'id')
            ->all();
    }

    /**
     * @return array<string, string> | null
     */
    protected static function getConfiguredScopeOptions(): ?array
    {
        $configured = config('filament-authz.role_resource.scope_options');

        if ($configured instanceof Closure) {
            /** @var mixed $configured */
            $configured = app()->call($configured);
        }

        if (! is_array($configured)) {
            return null;
        }

        return collect($configured)
            ->mapWithKeys(static fn (mixed $label, mixed $id): array => [(string) $id => (string) $label])
            ->all();
    }
}
