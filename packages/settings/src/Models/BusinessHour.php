<?php

declare(strict_types=1);

namespace Uptms\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $day_of_week
 * @property bool $is_open
 * @property string|null $opens_at HH:MM:SS
 * @property string|null $closes_at HH:MM:SS
 */
class BusinessHour extends Model
{
    protected $fillable = ['day_of_week', 'is_open', 'opens_at', 'closes_at'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'is_open' => 'boolean'];
    }
}
