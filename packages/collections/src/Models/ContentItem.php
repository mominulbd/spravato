<?php

declare(strict_types=1);

namespace Uptms\Collections\Models;

/**
 * Short repeated items: trust points (hero strip) and journey steps.
 *
 * @property string $group
 * @property string $title
 * @property string|null $body
 * @property string|null $icon
 * @property string|null $link_label
 * @property string|null $link_url
 */
class ContentItem extends CollectionItem
{
    protected $fillable = ['group', 'title', 'body', 'icon', 'link_label', 'link_url', 'sort_order'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
