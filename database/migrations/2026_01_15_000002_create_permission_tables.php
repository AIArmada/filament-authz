<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names', []);
        $columnNames = config('permission.column_names', []);
        $teams = (bool) config('permission.teams', false);

        throw_if($tableNames === [], 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        throw_if($teams && (($columnNames['team_foreign_key'] ?? 'team_id') === ''), 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        $permissionTable = $tableNames['permissions'] ?? 'permissions';
        $roleTable = $tableNames['roles'] ?? 'roles';
        $modelHasPermissionsTable = $tableNames['model_has_permissions'] ?? 'model_has_permissions';
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        $permissionColumnName = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $roleColumnName = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'team_id';

        Schema::create($permissionTable, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($roleTable, function (Blueprint $table) use ($teams, $teamForeignKey): void {
            $table->uuid('id')->primary();

            if ($teams) {
                $table->uuid($teamForeignKey)->nullable();
                $table->index($teamForeignKey, 'roles_team_foreign_key_index');
            }

            $table->string('name');
            $table->string('guard_name');
            $table->timestampsTz();

            if ($teams) {
                $table->unique([$teamForeignKey, 'name', 'guard_name']);

                return;
            }

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($modelHasPermissionsTable, function (Blueprint $table) use ($permissionColumnName, $modelMorphKey, $teams, $teamForeignKey): void {
            $table->uuid($permissionColumnName);
            $table->string('model_type');
            $table->uuid($modelMorphKey);
            $table->index([$modelMorphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');

            if ($teams) {
                $table->uuid($teamForeignKey)->nullable();
                $table->index($teamForeignKey, 'model_has_permissions_team_foreign_key_index');
                $table->unique([$teamForeignKey, $permissionColumnName, $modelMorphKey, 'model_type'], 'model_has_permissions_team_permission_model_type_unique');

                return;
            }

            $table->primary([$permissionColumnName, $modelMorphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($modelHasRolesTable, function (Blueprint $table) use ($roleColumnName, $modelMorphKey, $teams, $teamForeignKey): void {
            $table->uuid($roleColumnName);
            $table->string('model_type');
            $table->uuid($modelMorphKey);
            $table->index([$modelMorphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');

            if ($teams) {
                $table->uuid($teamForeignKey)->nullable();
                $table->index($teamForeignKey, 'model_has_roles_team_foreign_key_index');
                $table->unique([$teamForeignKey, $roleColumnName, $modelMorphKey, 'model_type'], 'model_has_roles_team_role_model_type_unique');

                return;
            }

            $table->primary([$roleColumnName, $modelMorphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($roleHasPermissionsTable, function (Blueprint $table) use ($roleColumnName, $permissionColumnName): void {
            $table->uuid($permissionColumnName);
            $table->uuid($roleColumnName);

            $table->primary([$permissionColumnName, $roleColumnName], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names', []);

        Schema::dropIfExists($tableNames['model_has_roles'] ?? 'model_has_roles');
        Schema::dropIfExists($tableNames['model_has_permissions'] ?? 'model_has_permissions');
        Schema::dropIfExists($tableNames['role_has_permissions'] ?? 'role_has_permissions');
        Schema::dropIfExists($tableNames['roles'] ?? 'roles');
        Schema::dropIfExists($tableNames['permissions'] ?? 'permissions');
    }
};
