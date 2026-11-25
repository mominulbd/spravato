<?php

declare(strict_types=1);

namespace Uptms\Permissions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $module
 * @property string $action
 * @property string $label
 * @property string|null $description
 */
class Permission extends Model
{
    protected $fillable = ['key', 'module', 'action', 'label', 'description'];
}
