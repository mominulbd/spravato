<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per site audit (Figma 19): nightly at 6:00 or "Run audit now".
 * Issues keep the keys of what they affect, so the overview can say how
 * many were fixed this week. Old rows are pruned after 90 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 10)->comment('running, done, failed');
            $table->string('trigger', 10)->comment('schedule, manual');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedSmallInteger('urls')->default(0)->comment('published URLs checked');
            $table->unsignedSmallInteger('indexable')->default(0);
            $table->unsignedSmallInteger('indexed')->nullable()->comment('per Search Console URL inspection');
            $table->json('issues')->nullable();
            $table->json('search')->nullable()->comment('Search Console: 28-day totals and top queries');
            $table->json('vitals')->nullable()->comment('Chrome UX Report, phones');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audits');
    }
};
