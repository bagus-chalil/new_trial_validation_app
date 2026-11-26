<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Structured IM parameter row. `section` says what it is (see ImSections);
 * value_min / value_max / unit carry numeric limits that IPC can evaluate.
 *
 * @property int $id
 * @property int $revision_id
 * @property string $section
 * @property int $sort_order
 * @property string|null $code
 * @property string $name
 * @property string|null $category
 * @property string|null $description
 * @property string|null $specification
 * @property string|null $frequency
 * @property string|null $value_min
 * @property string|null $value_max
 * @property string|null $unit
 */
class ImParameter extends Model
{
    protected $table = 'vd_im_parameters';

    protected $guarded = [];
}
