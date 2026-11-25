<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "Find out if you qualify for TMS" form: insurance plan and treatment
 * history are health information and stored encrypted like everything
 * else a person typed; the contact preference is a plain choice. All
 * nullable, because earlier requests did not ask.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table): void {
            $table->text('insurance')->nullable()->after('referred_by')->comment('encrypted');
            $table->text('prior_treatment')->nullable()->after('insurance')->comment('encrypted');
            $table->string('contact_method', 10)->nullable()->after('best_time');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table): void {
            $table->dropColumn(['insurance', 'prior_treatment', 'contact_method']);
        });
    }
};
