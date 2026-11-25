<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `origin` says which website form sent the request. (The self-check and its
 * encrypted `quiz_results` column were removed for Spravato before any deploy.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table): void {
            $table->string('origin', 10)->default('form')->after('topic');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table): void {
            $table->dropColumn('origin');
        });
    }
};
