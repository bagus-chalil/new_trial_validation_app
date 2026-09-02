<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinishedCheckSample extends Model
{
    // The AQL parameter groups from the legacy Finished Check form (~72 flattened
    // QST*/QSS*/QSP*/QSF* columns there, one row per group here). Exact wording not yet
    // verified against the source PDF screenshots — see ipc_app/CLAUDE.md.
    public const PARAMETER_KEYS = [
        'tersier_identity',
        'tersier_appearance',
        'tersier_coding_batch',
        'tersier_coding_na',
        'tersier_shipper_label',
        'secondary_identity',
        'secondary_appearance',
        'secondary_coding_batch',
        'secondary_coding_na',
        'secondary_attribute',
        'primary_packaging',
        'primary_capping_sealing',
        'primary_coding',
        'primary_coding_na',
        'primary_attribute',
        'functional_test',
        'special_test_bulk',
        'special_test_color',
        'special_test_odor',
    ];

    protected $fillable = [
        'finished_check_id',
        'parameter_key',
        'ac',
        'cd',
        'md',
        'mnd',
        'remark',
    ];

    public function finishedCheck()
    {
        return $this->belongsTo(FinishedCheck::class);
    }
}
