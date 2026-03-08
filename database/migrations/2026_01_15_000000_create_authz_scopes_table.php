<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authz_scopes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scopeable_type');
            $table->uuid('scopeable_id');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['scopeable_type', 'scopeable_id']);
            $table->index(['scopeable_type', 'scopeable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authz_scopes');
    }
};
