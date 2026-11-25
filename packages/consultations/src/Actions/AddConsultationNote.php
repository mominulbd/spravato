<?php

declare(strict_types=1);

namespace Uptms\Consultations\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Models\ConsultationNote;
use Uptms\Consultations\Models\ConsultationRequest;

/** A staff note. The audit log records that a note was added, never its text. */
final readonly class AddConsultationNote
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(ConsultationRequest $request, string $body, Authenticatable $author): ConsultationNote
    {
        return DB::transaction(function () use ($request, $body, $author): ConsultationNote {
            $note = new ConsultationNote(['body' => trim($body)]);
            $note->consultation_request_id = $request->id;
            $note->user_id = (int) $author->getAuthIdentifier();
            $note->save();
            $request->touch();
            $this->audit->record('consultation.note_added', $request, null, ['note_id' => $note->id]);

            return $note;
        });
    }
}
