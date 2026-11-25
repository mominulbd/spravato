<?php

declare(strict_types=1);

namespace Uptms\Pages\Policies;

use Uptms\Pages\Models\Page;
use Uptms\Permissions\Contracts\HasPermissions;
use Uptms\Publishing\Domain\Enums\PublishStatus;

/**
 * Editing the working copy never touches the site, so content.edit is
 * enough. Publishing, scheduling, unpublishing, moving or deleting a live
 * page need content.publish.
 */
class PagePolicy
{
    public function viewAny(HasPermissions $actor): bool
    {
        return $actor->hasPermission('content.edit');
    }

    public function create(HasPermissions $actor): bool
    {
        return $actor->hasPermission('content.edit');
    }

    public function update(HasPermissions $actor, Page $page): bool
    {
        return $actor->hasPermission('content.edit');
    }

    public function updateSettings(HasPermissions $actor, Page $page): bool
    {
        return $actor->hasPermission('content.edit')
            && ($page->status === PublishStatus::Draft || $actor->hasPermission('content.publish'));
    }

    public function publish(HasPermissions $actor, Page $page): bool
    {
        return $actor->hasPermission('content.publish');
    }

    public function delete(HasPermissions $actor, Page $page): bool
    {
        return ! $page->is_home && $this->updateSettings($actor, $page);
    }
}
