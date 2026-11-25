<?php

declare(strict_types=1);

namespace Uptms\Collections\Policies;

use Uptms\Collections\Models\CollectionItem;
use Uptms\Permissions\Contracts\HasPermissions;

/**
 * content.edit drafts and edits hidden items; anything visible on the
 * website — publishing, editing or deleting a published item, reordering a
 * list the site shows — needs content.publish.
 */
class CollectionItemPolicy
{
    public function viewAny(HasPermissions $actor): bool
    {
        return $actor->hasPermission('content.edit');
    }

    public function create(HasPermissions $actor): bool
    {
        return $actor->hasPermission('content.edit');
    }

    public function update(HasPermissions $actor, CollectionItem $item): bool
    {
        return $actor->hasPermission('content.edit') && (! $item->is_published || $actor->hasPermission('content.publish'));
    }

    public function delete(HasPermissions $actor, CollectionItem $item): bool
    {
        return $this->update($actor, $item);
    }

    public function publish(HasPermissions $actor): bool
    {
        return $actor->hasPermission('content.publish');
    }
}
