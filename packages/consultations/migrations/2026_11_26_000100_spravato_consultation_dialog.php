<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Spravato consultation dialog (DEVELOPMENT-PLAN.md, Phase 2) replaces
 * the TMS qualification form:
 *
 * - `patient_status`: new / existing / not sure (optional).
 * - `emergency_acknowledged`: the person ticked "I understand this form is
 *   not an emergency service" (required to send).
 * - `cta`: the label of the button that opened the dialog, e.g. "Ask about
 *   coverage & cost". The page's own wording, never anything typed.
 * - `prior_treatment` and `referred_by` go: the dialog asks for neither, and
 *   asks people not to share treatment history at all.
 *
 * Topics, best times and contact methods get the dialog's lists; rows
 * written by the old form are mapped onto the nearest new value.
 */
return new class extends Migration
{
    private const array TOPICS = [
        'tms_depression' => 'spravato',
        'ocd_specialty' => 'question',
        'loved_one' => 'question',
        'provider_referral' => 'question',
    ];

    private const array BEST_TIMES = [
        'anytime' => 'no_preference',
        'mornings' => 'morning',
        'afternoons' => 'afternoon',
    ];

    public function up(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table): void {
            $table->string('patient_status', 12)->nullable()->after('contact_method');
            $table->boolean('emergency_acknowledged')->default(false)->after('patient_status');
            $table->string('cta', 80)->nullable()->after('origin')->comment('label of the button that opened the dialog');
        });

        foreach (self::TOPICS as $old => $new) {
            DB::table('consultation_requests')->where('topic', $old)->update(['topic' => $new]);
        }

        foreach (self::BEST_TIMES as $old => $new) {
            DB::table('consultation_requests')->where('best_time', $old)->update(['best_time' => $new]);
        }

        DB::table('consultation_requests')->where('contact_method', 'text')->update(['contact_method' => 'phone']);

        Schema::table('consultation_requests', function (Blueprint $table): void {
            $table->dropColumn(['prior_treatment', 'referred_by']);
        });
    }

    public function down(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table): void {
            $table->text('referred_by')->nullable()->after('message')->comment('encrypted');
            $table->text('prior_treatment')->nullable()->after('insurance')->comment('encrypted');
        });

        DB::table('consultation_requests')->whereIn('topic', ['spravato', 'eligibility', 'first_visit'])->update(['topic' => 'tms_depression']);
        DB::table('consultation_requests')->where('topic', 'question')->update(['topic' => 'loved_one']);

        foreach (self::BEST_TIMES as $old => $new) {
            DB::table('consultation_requests')->where('best_time', $new)->update(['best_time' => $old]);
        }

        DB::table('consultation_requests')->where('best_time', 'early_evening')->update(['best_time' => 'anytime']);
        DB::table('consultation_requests')->where('contact_method', 'either')->update(['contact_method' => 'phone']);

        Schema::table('consultation_requests', function (Blueprint $table): void {
            $table->dropColumn(['patient_status', 'emergency_acknowledged', 'cta']);
        });
    }
};
