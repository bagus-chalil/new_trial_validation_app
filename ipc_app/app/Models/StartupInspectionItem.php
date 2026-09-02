<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StartupInspectionItem extends Model
{
    public const STATUS_OK = 'OK';

    public const STATUS_PARTIAL_OK = 'Partial OK';

    public const STATUS_NOT_OK = 'Not OK';

    // Exact wording not yet verified against the source PDF screenshots — see ipc_app/CLAUDE.md.
    public const PARAMETER_KEYS = [
        'bulk_color_texture',
        'bulk_odor',
        'appearance_after_filling',
        'leakage_test',
        'functional_test',
        'primer',
        'sekunder',
        'tersier',
        'attribute',
        'appearance',
    ];

    protected $fillable = [
        'startup_inspection_id',
        'parameter_key',
        'status',
        'remark',
    ];

    public function startupInspection()
    {
        return $this->belongsTo(StartupInspection::class);
    }
}
