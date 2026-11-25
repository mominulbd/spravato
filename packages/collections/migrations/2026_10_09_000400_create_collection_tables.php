<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collections (Figma 14–15). Every row is ordered (`sort_order`) and has a
 * visibility flag the public API filters on. Photos and logos reference the
 * media library; their usage is recorded so the file can't be deleted while
 * shown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('credentials', 60)->nullable();
            $table->string('role_title', 120);
            $table->text('bio')->nullable();
            $table->json('highlights')->nullable();
            $table->foreignId('photo_id')->nullable()->constrained('media')->nullOnDelete();
            $table->json('show_on')->nullable()->comment('page keys: home, tms, contact, quiz');
            $table->boolean('is_founder')->default(false);
            $table->string('schema_type', 20)->default('Person');
            $table->string('licence_number', 40)->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 80)->comment('home | tms | conditions/<slug> …');
            $table->string('question', 200);
            $table->text('answer');
            $table->boolean('in_schema')->default(true)->comment('included in FAQPage JSON-LD');
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'is_published', 'sort_order']);
        });

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->text('quote');
            $table->string('author_name', 120);
            $table->string('source_label', 120)->nullable();
            $table->string('source_url', 190)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('insurance_partners', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('logo_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('url', 190)->nullable();
            $table->string('note', 190)->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('content_items', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 40)->comment('trust_points | journey_steps');
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('link_label', 60)->nullable();
            $table->string('link_url', 190)->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('insurance_partners');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('team_members');
    }
};
