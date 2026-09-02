<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinishedCheckSample extends Model
{
    // The 19 AQL parameter groups from the legacy Finished Check form (76 flattened
    // QST*/QSS*/QSP*/QSF* x AC/CD/MD/MND columns there, one row per group here). Confirmed
    // against the real Power Apps export 2026-09-02 (ipc_app/app_legacy/): QSTI/QSTA/QSTCB/
    // QSTCN/QSTSL (Tersier), QSSI/QSSA/QSSCB/QSSCN/QSSAT (Secondary), QSPP/QSPCS/QSPC/QSPCN/
    // QSPA (Primary), QSFT (Functional Test), QSSTB/QSSTC/QSSTO (Special Test Bulk/Color/Odor).
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
