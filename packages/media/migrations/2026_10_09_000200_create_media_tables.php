<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media library (Figma 18 / 18b). `media.deleted_at` is the 30-day trash.
 * Usages are written by the modules that place a file (a page block, a team
 * photo), so the library can block deleting a file that is on the site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->string('disk', 20);
            $table->string('path', 255);
            $table->string('filename', 190)->comment('display name, editable');
            $table->string('original_name', 190);
            $table->string('mime', 80);
            $table->string('kind', 20)->comment('image | document');
            $table->unsignedBigInteger('size')->comment('bytes of the stored original');
            $table->unsignedBigInteger('original_size')->comment('bytes as uploaded');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt', 190)->nullable();
            $table->boolean('is_decorative')->default(false);
            $table->string('caption', 190)->nullable();
            $table->decimal('focal_x', 5, 2)->default(50);
            $table->decimal('focal_y', 5, 2)->default(50);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_used_at')->nullable();
            $table->timestamp('last_used_at')->nullable()->comment('when the last usage was removed');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['deleted_at', 'created_at']);
            $table->index(['kind', 'deleted_at']);
        });

        Schema::create('media_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('format', 10);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('path', 255);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->unique(['media_id', 'format', 'width']);
        });

        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('usable_type', 40);
            $table->unsignedBigInteger('usable_id');
            $table->string('context', 80)->comment('where on the owner, e.g. Hero');
            $table->string('label', 190)->comment('owner name shown in the library, e.g. Home');
            $table->string('admin_path', 190)->nullable()->comment('admin route to the owner');
            $table->timestamps();

            $table->unique(['media_id', 'usable_type', 'usable_id', 'context'], 'media_usages_unique');
            $table->index(['usable_type', 'usable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media');
        Schema::dropIfExists('media_folders');
    }
};
