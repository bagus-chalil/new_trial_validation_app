<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One tool row of a Check Tooling result (tool name, actual condition, status
 * OK / NOK / Need New / N/A).
 *
 * @property int $id
 * @property int $tooling_id
 * @property string|null $legacy_id
 * @property string $tool
 * @property string $actual
 * @property string $status
 * @property int $sort_order
 */
class ToolingItem extends Model
{
    protected $table = 'pm_tooling_items';

    protected $guarded = [];
}
