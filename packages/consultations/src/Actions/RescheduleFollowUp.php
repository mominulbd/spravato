<?php

declare(strict_types=1);

namespace Uptms\Consultations\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Models\ConsultationRequest;

/** "Follow-up by" (Figma 05): when the next call is due. */
final readonly class RescheduleFollowUp
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(ConsultationRequest $request, Carbon $at): ConsultationRequest
    {
        if ($request->follow_up_at?->equalTo($at)) {
            return $request;
        }

        DB::transaction(function () use ($request, $at): void {
            $old = $request->follow_up_at?->toIso8601String();
            $request->follow_up_at = $at->copy()->utc();
            $request->save();
            $this->audit->record('consultation.follow_up_changed', $request, ['follow_up_at' => $old], ['follow_up_at' => $request->follow_up_at->toIso8601String()]);
        });

        return $request;
    }
}
