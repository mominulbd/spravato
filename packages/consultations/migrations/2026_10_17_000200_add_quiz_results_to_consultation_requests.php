<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests can now come from the self-check (docs/QUIZ.md). `origin` says
 * which form sent it; `quiz_results` holds the answers, scores and feedback,
 * encrypted like everything else a person told the clinic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table): void {
            $table->string('origin', 10)->default('form')->after('topic');
            $table->mediumText('quiz_results')->nullable()->after('prior_treatment')->comment('encrypted JSON');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table): void {
            $table->dropColumn(['origin', 'quiz_results']);
        });
    }
};
