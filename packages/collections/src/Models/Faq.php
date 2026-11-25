<?php

declare(strict_types=1);

namespace Uptms\Collections\Models;

/**
 * @property string $group
 * @property string $question
 * @property string $answer
 * @property bool $in_schema
 */
class Faq extends CollectionItem
{
    protected $fillable = ['group', 'question', 'answer', 'in_schema', 'sort_order'];

    protected function casts(): array
    {
        return ['in_schema' => 'boolean', 'is_published' => 'boolean'];
    }
}
