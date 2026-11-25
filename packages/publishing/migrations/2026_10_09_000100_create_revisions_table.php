<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots of editable content (pages, the quiz). A `published` revision is
 * what the website serves; `draft` revisions are saved checkpoints staff can
 * restore. Rows are never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table): void {
            $table->id();
            $table->string('revisionable_type', 40);
            $table->unsignedBigInteger('revisionable_id');
            $table->string('kind', 20)->comment('draft | published | scheduled | restored');
            $table->json('payload');
            $table->string('note', 190)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['revisionable_type', 'revisionable_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
