<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One `seo_meta` row per page, post or condition (polymorphic). Empty
 * fields fall back to defaults built from the content and the title
 * templates in `seo_settings`. Saved values are live: SEO has its own
 * permission (seo.edit) rather than going through content publishing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table): void {
            $table->id();
            $table->string('seoable_type', 40);
            $table->unsignedBigInteger('seoable_id');
            $table->string('meta_title', 190)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('focus_keyphrase', 100)->nullable();
            $table->json('keyphrases')->nullable();
            $table->string('canonical_url', 190)->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->boolean('in_sitemap')->default(true);
            $table->string('breadcrumb_title', 80)->nullable();
            $table->string('max_image_preview', 10)->default('large');
            $table->unsignedSmallInteger('max_snippet')->nullable()->comment('null = no limit');
            $table->boolean('og_custom')->default(false);
            $table->string('og_title', 120)->nullable();
            $table->string('og_description', 240)->nullable();
            $table->foreignId('og_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('twitter_card', 30)->default('summary_large_image');
            $table->string('schema_type', 40)->nullable();
            $table->string('schema_about', 120)->nullable();
            $table->json('schema_disabled')->nullable()->comment('graph pieces switched off');
            $table->json('schema_custom')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('last_reviewed_at')->nullable();
            $table->json('citations')->nullable();
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedTinyInteger('readability_score')->nullable();
            $table->json('analysis')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
            $table->index('focus_keyphrase');
        });

        Schema::create('seo_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_settings');
        Schema::dropIfExists('seo_meta');
    }
};
