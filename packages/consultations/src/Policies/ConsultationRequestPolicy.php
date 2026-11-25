<?php

declare(strict_types=1);

namespace Uptms\Consultations\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Permissions\Contracts\HasPermissions;

/**
 * Second gate (AGENTS.md). Without consultations.view_all a person works
 * only the requests assigned to them; controllers answer NOT_FOUND for the
 * rest, so ids outside their scope reveal nothing.
 */
class ConsultationRequestPolicy
{
    public function viewAny(HasPermissions $actor): bool
    {
        return $actor->hasPermission('consultations.view');
    }

    public function view(HasPermissions&Authenticatable $actor, ConsultationRequest $request): bool
    {
        return $actor->hasPermission('consultations.view') && $request->isVisibleTo($actor);
    }

    public function update(HasPermissions&Authenticatable $actor, ConsultationRequest $request): bool
    {
        return $actor->hasPermission('consultations.update') && $this->view($actor, $request);
    }

    /** Handing work to someone else needs the whole inbox in view. */
    public function assign(HasPermissions $actor): bool
    {
        return $actor->hasPermission('consultations.update') && $actor->hasPermission('consultations.view_all');
    }

    public function export(HasPermissions $actor): bool
    {
        return $actor->hasPermission('consultations.export') && $actor->hasPermission('consultations.view_all');
    }

    public function delete(HasPermissions&Authenticatable $actor, ConsultationRequest $request): bool
    {
        return $actor->hasPermission('consultations.export') && $this->view($actor, $request);
    }
}
