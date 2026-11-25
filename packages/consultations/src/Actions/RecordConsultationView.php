<?php

declare(strict_types=1);

namespace Uptms\Consultations\Actions;

use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Models\ConsultationRequest;

/** Every time someone opens a request's decrypted details it is written to the audit log. */
final readonly class RecordConsultationView
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(ConsultationRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            if ($request->first_viewed_at === null) {
                $request->first_viewed_at = now();
                $request->timestamps = false;
                $request->save();
                $request->timestamps = true;
            }

            $this->audit->record('consultation.viewed', $request);
        });
    }
}
