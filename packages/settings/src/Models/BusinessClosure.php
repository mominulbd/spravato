<?php

declare(strict_types=1);

namespace Uptms\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property string $label
 */
class BusinessClosure extends Model
{
    protected $fillable = ['date', 'label'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
