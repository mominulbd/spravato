<?php

declare(strict_types=1);

namespace Uptms\Media\Policies;

use Uptms\Media\Models\Media;
use Uptms\Permissions\Contracts\HasPermissions;

/**
 * Editors (content.edit) browse and pick files; media.manage uploads,
 * edits, replaces and cleans up.
 */
class MediaPolicy
{
    public function viewAny(HasPermissions $actor): bool
    {
        return $actor->hasPermission('media.manage') || $actor->hasPermission('content.edit');
    }

    public function create(HasPermissions $actor): bool
    {
        return $actor->hasPermission('media.manage');
    }

    public function update(HasPermissions $actor, Media $media): bool
    {
        return $actor->hasPermission('media.manage');
    }

    public function delete(HasPermissions $actor, Media $media): bool
    {
        return $actor->hasPermission('media.manage');
    }
}
