<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Console\Concerns\Prohibitable;
use AIArmada\FilamentAuthz\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Assign super admin role to a user.
 *
 * Features:
 * - Search for existing users
 * - Create user if needed
 * - Multi-guard support
 */
class SuperAdminCommand extends Command
{
    use Prohibitable;

    protected $signature = 'authz:super-admin
        {--user= : ID of user to assign super admin role}
        {--create : Create a new user}
        {--panel= : Panel ID for guard configuration}';

    protected $description = 'Assign the super admin role to a user.';

    public function handle(): int
    {
        $this->initializeProhibitable();

        $superAdminRole = (string) config('filament-authz.super_admin_role', 'super_admin');
        $guards = (array) config('filament-authz.guards', ['web']);
        $guard = $guards[0] ?? 'web';

        $user = $this->getOrCreateUser($guard);

        if ($user === null) {
            return self::FAILURE;
        }

        foreach ($guards as $g) {
            Role::findOrCreate($superAdminRole, $g);
        }

        if (method_exists($user, 'assignRole')) {
            $user->assignRole($superAdminRole);
            info("✓ Assigned '{$superAdminRole}' role to user: {$this->getUserIdentifier($user)}");
        } else {
            warning('User model does not have HasRoles trait. Please add Spatie\\Permission\\Traits\\HasRoles to your User model.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return (Authenticatable&Model)|null
     */
    protected function getOrCreateUser(string $guard): Authenticatable | Model | null
    {
        $userModel = $this->getUserModel($guard);

        if ($this->option('create')) {
            return $this->createUser($userModel);
        }

        $userId = $this->option('user');

        if ($userId !== null) {
            /** @var (Authenticatable&Model)|null $user */
            $user = $userModel::find($userId);

            if ($user === null) {
                warning("User with ID '{$userId}' not found.");

                return null;
            }

            return $user;
        }

        return $this->searchForUser($userModel);
    }

    /**
     * @param  class-string<Model&Authenticatable>  $userModel
     * @return (Authenticatable&Model)|null
     */
    protected function searchForUser(string $userModel): Authenticatable | Model | null
    {
        $emailColumn = $this->getEmailColumn($userModel);

        $userId = search(
            label: 'Search for a user by email',
            options: function (string $search) use ($userModel, $emailColumn): array {
                if (mb_strlen($search) < 2) {
                    return [];
                }

                return $userModel::query()
                    ->where($emailColumn, 'like', "%{$search}%")
                    ->limit(10)
                    ->get()
                    ->mapWithKeys(fn ($user): array => [
                        $user->getKey() => $user->{$emailColumn},
                    ])
                    ->toArray();
            },
            placeholder: 'Type to search...',
        );

        if ($userId === null || $userId === '') {
            warning('No user selected.');

            return null;
        }

        /** @var (Authenticatable&Model)|null */
        return $userModel::find($userId);
    }

    /**
     * @param  class-string<Model&Authenticatable>  $userModel
     * @return (Authenticatable&Model)|null
     */
    protected function createUser(string $userModel): Authenticatable | Model | null
    {
        $emailColumn = $this->getEmailColumn($userModel);
        $nameColumn = $this->getNameColumn($userModel);

        $name = text(
            label: 'Name',
            required: true,
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'Please enter a valid email address.'
                : null,
        );

        /** @var (Authenticatable&Model)|null $existingUser */
        $existingUser = $userModel::query()->where($emailColumn, $email)->first();

        if ($existingUser !== null) {
            warning("User with email '{$email}' already exists.");

            return $existingUser;
        }

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value): ?string => mb_strlen($value) < 8
                ? 'Password must be at least 8 characters.'
                : null,
        );

        /** @var Authenticatable&Model $user */
        $user = new $userModel;
        $user->{$nameColumn} = $name;
        $user->{$emailColumn} = $email;
        $user->setAttribute('password', Hash::make($password));
        $user->save();

        info("✓ Created user: {$email}");

        return $user;
    }

    /**
     * @return class-string<Model&Authenticatable>
     */
    protected function getUserModel(string $guard): string
    {
        $provider = config("auth.guards.{$guard}.provider");

        /** @var class-string<Model&Authenticatable> */
        return config("auth.providers.{$provider}.model", 'App\\Models\\User');
    }

    /**
     * @param  class-string  $userModel
     */
    protected function getEmailColumn(string $userModel): string
    {
        if (method_exists($userModel, 'getEmailForVerification')) {
            return 'email';
        }

        return 'email';
    }

    /**
     * @param  class-string  $userModel
     */
    protected function getNameColumn(string $userModel): string
    {
        return 'name';
    }

    /**
     * @param  Authenticatable&Model  $user
     */
    protected function getUserIdentifier(Authenticatable $user): string
    {
        if (method_exists($user, 'getEmailForVerification')) {
            return $user->getEmailForVerification();
        }

        return (string) $user->getAuthIdentifier();
    }
}
