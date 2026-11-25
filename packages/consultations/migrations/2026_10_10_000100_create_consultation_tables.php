<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultation requests are health information. Everything a person typed
 * is stored encrypted (Laravel `encrypted` casts, AES-256); staff search
 * through keyed hashes in `search_index`, never plaintext. No IP address
 * is stored. Rows are deleted outright when staff delete them or when the
 * retention period ends (consultations:purge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 20)->nullable()->unique()->comment('CR-2026-0042, set after insert');
            $table->text('name')->comment('encrypted');
            $table->text('phone')->comment('encrypted');
            $table->text('email')->nullable()->comment('encrypted');
            $table->text('message')->nullable()->comment('encrypted');
            $table->text('referred_by')->nullable()->comment('encrypted');
            $table->string('topic', 30);
            $table->string('best_time', 20)->nullable();
            $table->string('status', 20)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('follow_up_at')->nullable()->comment('target call-back time');
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('source_page', 190)->nullable();
            $table->string('previous_page', 190)->nullable()->comment('the site page visited just before');
            $table->string('referrer_host', 120)->nullable()->comment('external referrer, host only');
            $table->json('utm')->nullable();
            $table->string('device', 60)->nullable()->comment('coarse: Mobile · Safari');
            $table->char('phone_hash', 64)->comment('keyed hash: duplicate check');
            $table->text('search_index')->comment('keyed token hashes for search');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['phone_hash', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('consultation_request_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->comment('encrypted');
            $table->timestamps();

            $table->index(['consultation_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_request_notes');
        Schema::dropIfExists('consultation_requests');
    }
};
