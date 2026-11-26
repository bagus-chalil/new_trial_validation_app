<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $mixing_id
 * @property int $parameter_id
 * @property string $decision
 * @property string|null $result_value
 * @property string|null $remark
 */
class MixingResult extends Model
{
    protected $table = 'vd_mixing_results';

    protected $guarded = [];
}
