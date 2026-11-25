<?php

declare(strict_types=1);

namespace Uptms\Consultations\Actions;

use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Models\ConsultationRequest;

/**
 * Permanent deletion: the person's details and the notes go; the audit
 * log keeps only the reference and who deleted it.
 */
final readonly class DeleteConsultationRequest
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(ConsultationRequest $request, string $reason = 'deleted'): void
    {
        DB::transaction(function () use ($request, $reason): void {
            $this->audit->record('consultation.'.$reason, $request, ['status' => $request->status->value]);
            $request->delete();
        });
    }
}
