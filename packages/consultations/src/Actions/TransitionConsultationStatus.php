<?php

declare(strict_types=1);

namespace Uptms\Consultations\Actions;

use App\Support\Exceptions\InvalidStateTransitionException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Models\ConsultationRequest;

/**
 * The one place a request's status changes. The first time a request is
 * contacted, scheduled or closed the time is recorded; whoever first
 * contacts an unassigned request becomes its owner.
 */
final readonly class TransitionConsultationStatus
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(ConsultationRequest $request, ConsultationStatus $to, Authenticatable $actor): ConsultationRequest
    {
        $from = $request->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidStateTransitionException::make('This request', $from, $to, $from->allowedTransitions());
        }

        DB::transaction(function () use ($request, $from, $to, $actor): void {
            $request->status = $to;
            $column = $to->timestampColumn();

            if ($column !== null && $request->getAttribute($column) === null) {
                $request->setAttribute($column, now());
            }

            $old = ['status' => $from->value];
            $new = ['status' => $to->value];

            if ($request->assigned_to === null && in_array($to, [ConsultationStatus::Contacted, ConsultationStatus::Scheduled], true)) {
                $request->assigned_to = (int) $actor->getAuthIdentifier();
                $old['assigned_to'] = null;
                $new['assigned_to'] = $request->assigned_to;
            }

            $request->save();
            $this->audit->record('consultation.status_changed', $request, $old, $new);
        });

        return $request;
    }
}
