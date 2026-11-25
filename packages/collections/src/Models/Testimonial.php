<?php

declare(strict_types=1);

namespace Uptms\Collections\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $quote
 * @property string $author_name
 * @property string|null $source_label
 * @property string|null $source_url
 * @property int|null $rating
 */
class Testimonial extends CollectionItem
{
    use SoftDeletes;

    protected $fillable = ['quote', 'author_name', 'source_label', 'source_url', 'rating', 'sort_order'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_published' => 'boolean'];
    }
}
