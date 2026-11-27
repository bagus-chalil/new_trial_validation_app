<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Cosmax plant / factory site (machine master location level 1).
 *
 * @property int|null $deleted_by
 */
#[Fillable(['code', 'name', 'address', 'is_active'])]
class Plant extends Model
{
    use SoftDeletes;

    protected $table = 'mc_plants';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
