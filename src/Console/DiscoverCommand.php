<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class DiscoverCommand extends Command
{
    protected $signature = 'authz:discover
        {--panel= : The panel ID to discover entities from}
        {--create : Create discovered permissions in database}
        {--dry-run : Show what would be created without creating}';

    protected $description = 'Discover Filament entities and generate permissions.';

    public function handle(EntityDiscoveryService $discovery): int
    {
        $panelId = $this->option('panel');

        if ($panelId === null) {
            $panels = collect(Filament::getPanels())->keys()->all();

            if (count($panels) === 0) {
                warning('No Filament panels found.');

                return self::FAILURE;
            }

            if (count($panels) === 1) {
                $panelId = $panels[0];
            } else {
                $panelId = select(
                    label: 'Which panel do you want to discover entities from?',
                    options: $panels,
                );
            }
        }

        $panel = Filament::getPanel($panelId);

        if ($panel === null) {
            warning("Panel '{$panelId}' not found.");

            return self::FAILURE;
        }

        Filament::setCurrentPanel($panel);

        $entities = $discovery->discover($panel);

        if ($entities->isEmpty()) {
            warning('No entities discovered.');

            return self::SUCCESS;
        }

        info("Discovered {$entities->count()} permissions from panel '{$panelId}':");

        $grouped = $entities->groupBy('type');

        foreach ($grouped as $type => $items) {
            $this->newLine();
            info(str($type)->plural()->headline()->toString() . " ({$items->count()}):");

            table(
                headers: ['Permission', 'Label'],
                rows: $items->map(fn (array $item): array => [
                    $item['permission'],
                    $item['label'],
                ])->all(),
            );
        }

        if ($this->option('dry-run')) {
            info('Dry run complete. No permissions were created.');

            return self::SUCCESS;
        }

        if ($this->option('create') || confirm('Create these permissions in the database?', default: false)) {
            $this->createPermissions($entities->pluck('permission')->unique()->all());
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function createPermissions(array $permissions): void
    {
        $guards = $this->validateGuards((array) config('filament-authz.guards', ['web']));

        if ($guards === []) {
            warning('No valid guards configured.');

            return;
        }

        $created = 0;

        foreach ($permissions as $permission) {
            foreach ($guards as $guard) {
                Permission::findOrCreate($permission, $guard);
                $created++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        info("Created {$created} permissions across " . count($guards) . ' guard(s).');
    }

    /**
     * @param  list<string>  $guards
     * @return list<string>
     */
    protected function validateGuards(array $guards): array
    {
        $configuredGuards = array_keys((array) config('auth.guards', []));

        return array_values(array_filter(
            $guards,
            fn (string $guard): bool => in_array($guard, $configuredGuards, true)
        ));
    }
}
