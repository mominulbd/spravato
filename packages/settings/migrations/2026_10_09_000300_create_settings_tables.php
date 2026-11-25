<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Typed key/value settings (the keys and their rules live in
 * SettingsRegistry), weekly office hours and one-off closures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 40);
            $table->string('key', 60);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });

        Schema::create('business_hours', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->unique()->comment('ISO: 1 = Monday … 7 = Sunday');
            $table->boolean('is_open')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('business_closures', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->string('label', 80);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_closures');
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('settings');
    }
};
