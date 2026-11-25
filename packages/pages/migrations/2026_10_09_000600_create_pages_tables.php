<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pages and their blocks (Figma 06–07). `page_blocks` is the working copy
 * staff edit; the website reads the revision `published_revision_id`
 * points at, so autosaves never reach the live site. `lock_version` stops
 * two editors overwriting each other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('title', 120);
            $table->string('slug', 80);
            $table->string('path', 190)->unique()->comment('/ for home, /parent/slug otherwise');
            $table->string('template', 20);
            $table->string('status', 20)->default('draft');
            $table->boolean('is_home')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('published_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->foreignId('scheduled_revision_id')->nullable()->constrained('revisions')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->char('draft_hash', 40)->nullable();
            $table->char('published_hash', 40)->nullable();
            $table->timestamp('content_updated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['template', 'deleted_at']);
        });

        Schema::create('page_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('type', 40);
            $table->json('data');
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_blocks');
        Schema::dropIfExists('pages');
    }
};
