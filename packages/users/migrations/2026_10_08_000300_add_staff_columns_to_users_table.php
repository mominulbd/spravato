<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The framework creates `users`; this makes it the staff table
 * (docs/DATABASE.md): one role, activation, invitations, TOTP 2FA, soft delete.
 * `password` is null until an invited user accepts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid()->nullable()->unique()->after('id');
            $table->foreignId('role_id')->nullable()->after('email')->constrained('roles')->restrictOnDelete();
            $table->string('password')->nullable()->change();
            $table->boolean('is_active')->default(true)->after('password');
            $table->text('two_factor_secret')->nullable()->after('is_active')->comment('encrypted');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret')->comment('encrypted, hashed codes');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);
        });

        Schema::create('user_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('token_hash', 64)->unique()->comment('sha256 of the emailed token');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
            $table->dropIndex(['is_active', 'deleted_at']);
            $table->dropColumn([
                'uuid', 'is_active', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'last_login_at', 'last_seen_at', 'deleted_at',
            ]);
        });
    }
};
