<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\Authz\Console\Concerns\Prohibitable;
use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use AIArmada\FilamentAuthz\Facades\FilamentAuthz;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

/**
 * Generate a seeder file from existing roles and permissions.
 *
 * Features:
 * - Cleaner seeder output
 * - Option for permissions only or with roles
 * - Better formatting
 */
class SeederCommand extends Command
{
    use Prohibitable;

    protected $signature = 'authz:seeder
        {--option=all : What to include (all|permissions|roles)}
        {--panel= : Panel ID to discover from}
        {--generate : Generate permissions from entities first}
        {--force : Overwrite existing seeder}';

    protected $description = 'Generate a seeder file from existing roles and permissions.';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->initializeProhibitable()) {
            return self::FAILURE;
        }

        $option = $this->option('option');

        if ($this->option('generate')) {
            $this->generatePermissions();
        }

        $permissions = $this->getPermissions();
        $roles = $this->getRoles();

        if ($permissions->isEmpty() && $roles->isEmpty()) {
            warning('No roles or permissions found. Run with --generate to create from entities first.');

            return self::FAILURE;
        }

        $seederPath = database_path('seeders/AuthzSeeder.php');

        if ($this->files->exists($seederPath) && ! $this->option('force')) {
            if (! confirm('AuthzSeeder.php already exists. Overwrite?', default: false)) {
                info('Seeder generation cancelled.');

                return self::SUCCESS;
            }
        }

        $content = $this->generateSeederContent($permissions, $roles, $option);

        $this->files->ensureDirectoryExists(dirname($seederPath));
        $this->files->put($seederPath, $content);

        $this->newLine();
        note('<fg=green;options=bold>Seeder Generated:</>');
        info("Path: {$seederPath}");
        info("Permissions: {$permissions->count()}");
        info("Roles: {$roles->count()}");
        $this->newLine();
        info('Run: php artisan db:seed --class=AuthzSeeder');

        return self::SUCCESS;
    }

    protected function generatePermissions(): void
    {
        $panelId = $this->option('panel');

        if ($panelId === null) {
            $panels = collect(Filament::getPanels())->keys()->all();

            if (count($panels) === 1) {
                $panelId = $panels[0];
            } elseif (count($panels) > 1) {
                $panelId = select(
                    label: 'Which panel to generate permissions from?',
                    options: $panels,
                );
            }
        }

        if ($panelId !== null) {
            $panel = Filament::getPanel($panelId);

            if ($panel === null) {
                warning("Panel '{$panelId}' not found.");

                return;
            }

            Filament::setCurrentPanel($panel);

            $guards = (array) config('authz.guards', ['web']);
            $permissions = FilamentAuthz::getAllPermissions($panel);

            foreach ($permissions as $permission) {
                foreach ($guards as $guard) {
                    Permission::findOrCreate($permission, $guard);
                }
            }

            info('Generated ' . count($permissions) . " permissions from panel '{$panelId}'.");
        }
    }

    /**
     * @return Collection<int, Permission>
     */
    protected function getPermissions(): Collection
    {
        return Permission::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Role>
     */
    protected function getRoles(): Collection
    {
        return Role::query()->with('permissions')->orderBy('name')->get();
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @param  Collection<int, Role>  $roles
     */
    protected function generateSeederContent(Collection $permissions, Collection $roles, string $option): string
    {
        $permissionArray = $this->formatPermissionsArray($permissions);
        $rolesArray = $this->formatRolesArray($roles);

        $includePermissions = in_array($option, ['all', 'permissions'], true);
        $includeRoles = in_array($option, ['all', 'roles'], true);

        $permissionsCode = $includePermissions ? $this->generatePermissionsCode($permissionArray) : '';
        $rolesCode = $includeRoles ? $this->generateRolesCode($rolesArray) : '';

        return <<<PHP
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Authz Seeder - Generated by authz:seeder command.
 */
class AuthzSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

{$permissionsCode}
{$rolesCode}
    }
}
PHP;
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return array<string, list<string>>
     */
    protected function formatPermissionsArray(Collection $permissions): array
    {
        $result = [];

        foreach ($permissions as $permission) {
            $guard = (string) $permission->getAttribute('guard_name');

            if (! isset($result[$guard])) {
                $result[$guard] = [];
            }

            $result[$guard][] = $permission->name;
        }

        return $result;
    }

    /**
     * @param  Collection<int, Role>  $roles
     * @return array<string, array{guard: string, permissions: list<string>}>
     */
    protected function formatRolesArray(Collection $roles): array
    {
        $result = [];

        foreach ($roles as $role) {
            $result[$role->name] = [
                'guard' => (string) $role->getAttribute('guard_name'),
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, list<string>>  $permissions
     */
    protected function generatePermissionsCode(array $permissions): string
    {
        if (empty($permissions)) {
            return '';
        }

        $code = "        // Create permissions\n";

        foreach ($permissions as $guard => $perms) {
            $guardLiteral = var_export($guard, true);
            $code .= "        \$guardPermissions = [\n";
            foreach ($perms as $perm) {
                $code .= '            ' . var_export($perm, true) . ",\n";
            }
            $code .= "        ];\n\n";
            $code .= "        foreach (\$guardPermissions as \$permission) {\n";
            $code .= "            Permission::findOrCreate(\$permission, {$guardLiteral});\n";
            $code .= "        }\n\n";
        }

        return $code;
    }

    /**
     * @param  array<string, array{guard: string, permissions: list<string>}>  $roles
     */
    protected function generateRolesCode(array $roles): string
    {
        if (empty($roles)) {
            return '';
        }

        $code = "        // Create roles and assign permissions\n";

        foreach ($roles as $roleName => $data) {
            $guard = $data['guard'];
            $permissions = $data['permissions'];

            // Role, permission, and guard names are admin-controlled; var_export()
            // keeps the generated string literals valid PHP. A single reused
            // variable avoids collisions between sanitized role names.
            $code .= '        $role = Role::findOrCreate(' . var_export($roleName, true) . ', ' . var_export($guard, true) . ");\n";

            if (! empty($permissions)) {
                $code .= "        \$role->syncPermissions([\n";
                foreach ($permissions as $perm) {
                    $code .= '            ' . var_export($perm, true) . ",\n";
                }
                $code .= "        ]);\n";
            }

            $code .= "\n";
        }

        return $code;
    }
}
