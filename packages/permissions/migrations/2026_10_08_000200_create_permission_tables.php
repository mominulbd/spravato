<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and the permission registry (docs/DATABASE.md). A user holds one
 * role (`users.role_id`, added by uptms.users), as in Figma screen 26.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique()->comment('{module}.{action}');
            $table->string('module', 40)->index();
            $table->string('action', 40);
            $table->string('label');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('description', 500)->nullable();
            $table->boolean('is_system')->default(false)->comment('Seeded roles cannot be deleted');
            $table->boolean('requires_two_factor')->default(true)->comment('Holders must confirm TOTP before using the admin');
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
