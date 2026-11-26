<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Customer / brand code used inside IM numbers.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class ImBrandCode extends Model
{
    protected $table = 'vd_im_brand_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
