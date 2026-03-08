<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Console\Concerns\Prohibitable;
use AIArmada\FilamentAuthz\Facades\Authz;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

/**
 * Generate Laravel policies for Filament resources.
 *
 * Features:
 * - Cleaner policy stubs
 * - Support for UUID models
 * - Proper type hints
 */
class GeneratePoliciesCommand extends Command
{
    use Prohibitable;

    protected $signature = 'authz:policies
        {--panel= : The panel ID}
        {--resource=* : Specific resources (class basenames)}
        {--path= : Custom policy path}
        {--force : Overwrite existing policies}';

    protected $description = 'Generate Laravel policies for Filament resources.';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->initializeProhibitable();

        $panelId = $this->getPanelId();

        if ($panelId === null) {
            return self::FAILURE;
        }

        $panel = Filament::getPanel($panelId);
        Filament::setCurrentPanel($panel);

        $resources = $this->getTargetResources($panel);

        if ($resources->isEmpty()) {
            warning('No resources found to generate policies for.');

            return self::SUCCESS;
        }

        $path = $this->option('path') ?: app_path('Policies');
        $force = (bool) $this->option('force');

        $this->files->ensureDirectoryExists($path);

        $generated = 0;
        $skipped = 0;

        foreach ($resources as $resource) {
            $modelClass = $resource['model'] ?? null;

            if ($modelClass === null) {
                continue;
            }

            $policyPath = $path . '/' . class_basename($modelClass) . 'Policy.php';

            if ($this->files->exists($policyPath) && ! $force) {
                $skipped++;

                continue;
            }

            $this->generatePolicy($modelClass, $resource['permissions'], $policyPath);
            $generated++;
        }

        $this->newLine();
        note('<fg=green;options=bold>Policy Generation Summary:</>');
        info("Generated: {$generated} policies");

        if ($skipped > 0) {
            info("Skipped: {$skipped} (already exist, use --force to overwrite)");
        }

        info("Path: {$path}");

        return self::SUCCESS;
    }

    protected function getPanelId(): ?string
    {
        $panelId = $this->option('panel');

        if ($panelId !== null) {
            return $panelId;
        }

        $panels = collect(Filament::getPanels())->keys()->all();

        if (count($panels) === 0) {
            warning('No Filament panels found.');

            return null;
        }

        if (count($panels) === 1) {
            return $panels[0];
        }

        return select(
            label: 'Which panel?',
            options: $panels,
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function getTargetResources(\Filament\Panel $panel): \Illuminate\Support\Collection
    {
        $resources = Authz::getResources($panel);
        $targetNames = $this->option('resource');

        if (empty($targetNames)) {
            return $resources;
        }

        return $resources->filter(function (array $resource) use ($targetNames): bool {
            $basename = class_basename($resource['class']);

            return in_array($basename, $targetNames, true)
                || in_array(str_replace('Resource', '', $basename), $targetNames, true);
        });
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<string, string>  $permissions
     */
    protected function generatePolicy(string $modelClass, array $permissions, string $path): void
    {
        $modelBasename = class_basename($modelClass);
        $userModel = $this->getUserModel();
        $userBasename = class_basename($userModel);

        $stub = $this->getPolicyStub();

        $methods = $this->generatePolicyMethods($modelClass, $permissions);

        $content = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ userModel }}', '{{ userClass }}', '{{ model }}', '{{ modelVariable }}', '{{ methods }}'],
            ['App\\Policies', $modelBasename . 'Policy', $userModel, $userBasename, $modelClass, Str::camel($modelBasename), $methods],
            $stub
        );

        $this->files->put($path, $content);
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<string, string>  $permissions
     */
    protected function generatePolicyMethods(string $modelClass, array $permissions): string
    {
        $modelBasename = class_basename($modelClass);
        $modelVariable = Str::camel($modelBasename);
        $userModel = class_basename($this->getUserModel());

        $methods = [];

        foreach ($permissions as $permission => $label) {
            $action = Str::afterLast($permission, config('filament-authz.permissions.separator', '.'));
            $methodName = Str::camel($action);

            $needsModel = in_array($methodName, ['view', 'update', 'delete', 'restore', 'forceDelete', 'replicate'], true);

            if ($needsModel) {
                $methods[] = <<<PHP
    /**
     * Determine whether the user can {$action} the model.
     */
    public function {$methodName}({$userModel} \$user, {$modelBasename} \${$modelVariable}): bool
    {
        return \$user->can('{$permission}');
    }
PHP;
            } else {
                $methods[] = <<<PHP
    /**
     * Determine whether the user can {$action}.
     */
    public function {$methodName}({$userModel} \$user): bool
    {
        return \$user->can('{$permission}');
    }
PHP;
            }
        }

        return implode("\n\n", $methods);
    }

    protected function getPolicyStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use {{ userModel }};
use {{ model }};
use Illuminate\Auth\Access\HandlesAuthorization;

class {{ class }}
{
    use HandlesAuthorization;

{{ methods }}
}
STUB;
    }

    protected function getUserModel(): string
    {
        $guard = config('filament-authz.guards.0', 'web');
        $provider = config("auth.guards.{$guard}.provider");

        return config("auth.providers.{$provider}.model", 'App\\Models\\User');
    }
}
