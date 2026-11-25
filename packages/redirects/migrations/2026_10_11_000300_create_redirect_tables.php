<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redirect rules the website applies before rendering, with daily hit
 * counts for the "Hits · 30 d" column, and the 404 log. Neither stores an
 * IP address or a full referring URL: only a referrer kind and, for links
 * from the site itself, the linking path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('from_path', 190)->unique();
            $table->string('to_path', 300)->nullable()->comment('null for 410 Gone');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->string('match_type', 10)->default('exact')->comment('exact · wildcard (a trailing * captured as $1)');
            $table->boolean('is_enabled')->default(true);
            $table->string('source', 20)->default('manual')->comment('manual · slug_change · imported · not_found');
            $table->string('note', 190)->nullable();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_enabled', 'match_type']);
            $table->index('source');
        });

        Schema::create('redirect_hits', function (Blueprint $table): void {
            $table->foreignId('redirect_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('hits')->default(0);
            $table->primary(['redirect_id', 'day']);
        });

        Schema::create('not_found_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('path', 190)->unique();
            $table->unsignedBigInteger('hits')->default(0);
            $table->unsignedBigInteger('google_hits')->default(0);
            $table->string('referrer_kind', 12)->default('direct')->comment('google · search · internal · external · direct (most recent)');
            $table->string('referrer_path', 190)->nullable()->comment('the site page that links here');
            $table->string('status', 10)->default('open')->comment('open · ignored · resolved');
            $table->boolean('is_bot')->default(false);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_seen_at']);
        });

        Schema::create('not_found_hits', function (Blueprint $table): void {
            $table->foreignId('not_found_log_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('google_hits')->default(0);
            $table->primary(['not_found_log_id', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_hits');
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('redirect_hits');
        Schema::dropIfExists('redirects');
    }
};
