<?php

declare(strict_types=1);

namespace Uptms\Consultations\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Users\Services\StaffDirectory;

/** Assign or unassign. Only active staff who can see requests may be given one. */
final readonly class AssignConsultationRequest
{
    public function __construct(private StaffDirectory $staff, private AuditRecorder $audit) {}

    public function execute(ConsultationRequest $request, ?int $userId): ConsultationRequest
    {
        if ($userId !== null && ! $this->staff->holds($userId, 'consultations.view')) {
            throw new GenericDomainException(ErrorCode::ValidationFailed, 'Choose a teammate who can see consultation requests.', [
                'assigned_to' => ['This person cannot be assigned requests.'],
            ]);
        }

        if ($request->assigned_to === $userId) {
            return $request;
        }

        DB::transaction(function () use ($request, $userId): void {
            $old = $request->assigned_to;
            $request->assigned_to = $userId;
            $request->save();
            $this->audit->record('consultation.assigned', $request, ['assigned_to' => $old], ['assigned_to' => $userId]);
        });

        return $request;
    }
}
