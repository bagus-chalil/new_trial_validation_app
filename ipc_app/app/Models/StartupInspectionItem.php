<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StartupInspectionItem extends Model
{
    public const STATUS_OK = 'OK';

    public const STATUS_PARTIAL_OK = 'Partial OK';

    public const STATUS_NOT_OK = 'Not OK';

    public const STATUS_NA = 'N/A';

    public const STATUSES = [self::STATUS_OK, self::STATUS_PARTIAL_OK, self::STATUS_NOT_OK];

    /**
     * Items that may also be answered N/A — Sekunder, since not every product has secondary
     * packaging (user, 2026-10-05) — and Leakage Test, since some products have no such test
     * (user, 2026-10-12). Every other item stays OK / Partial OK / Not OK.
     */
    public const NOT_APPLICABLE_KEYS = ['leakage_test', 'sekunder', 'attribute'];

    /**
     * Items left out of the Filling-only flow: it has no tertiary packaging step, so Tersier
     * isn't asked there (user, 2026-10-27).
     */
    public const FILLING_FLOW_EXCLUDED_KEYS = ['tersier'];

    /**
     * @return list<string>
     */
    public static function parameterKeysFor(IpcBatch $batch): array
    {
        if ($batch->flow_type !== IpcBatch::FLOW_FILLING) {
            return self::PARAMETER_KEYS;
        }

        return array_values(array_diff(self::PARAMETER_KEYS, self::FILLING_FLOW_EXCLUDED_KEYS));
    }

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
