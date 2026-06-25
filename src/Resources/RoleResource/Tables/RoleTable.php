<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Tables;

use Closure;
use Filament\Actions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;

final class RoleTable
{
    /**
     * @param  class-string  $modelClass
     */
    public static function configure(Table $table, string $modelClass): Table
    {
        $guards = config('filament-authz.guards', ['web']);
        $filters = [
            SelectFilter::make('guard_name')
                ->label(__('filament-authz::filament-authz.filter.guard'))
                ->options(array_combine($guards, $guards))
                ->placeholder(__('filament-authz::filament-authz.filter.all_guards'))
                ->searchable(),
        ];

        if (config('authz.scopes.enabled', false) && config('filament-authz.central_app', false)) {
            $teamsKey = app(PermissionRegistrar::class)->teamsKey;
            $scopeOptions = self::getScopeOptions();

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

        if (config('authz.scopes.enabled', false) && config('filament-authz.central_app', false)) {
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

        return $table
            ->columns($columns)
            ->filters($filters)
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
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
            ->emptyStateHeading(__('filament-authz::filament-authz.empty_state.heading'))
            ->emptyStateDescription(__('filament-authz::filament-authz.empty_state.description'))
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    /**
     * @return array<string, string>
     */
    private static function getScopeOptions(): array
    {
        $configured = config('filament-authz.role_resource.scope_options');

        if ($configured instanceof Closure) {
            /** @var mixed $configured */
            $configured = app()->call($configured);
        }

        if (! is_array($configured)) {
            return [];
        }

        return collect($configured)
            ->mapWithKeys(static fn (mixed $label, mixed $id): array => [(string) $id => (string) $label])
            ->all();
    }
}
