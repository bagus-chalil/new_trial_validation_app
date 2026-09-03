<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingCheck extends Model
{
    public const STATUS_CONFORM = 'Conform';

    public const STATUS_NOT_CONFORM = 'Not Conform';

    public const STATUS_NA = 'N/A';

    public const DECISION_PASSED = 'Passed';

    public const DECISION_HOLD = 'Hold';

    public const DECISION_REJECT = 'Reject';

    public const DECISIONS = [
        self::DECISION_PASSED,
        self::DECISION_HOLD,
        self::DECISION_REJECT,
    ];

    /**
     * Real 13-item checklist confirmed against the Power Apps export 2026-09-02
     * (ipc_app/app_legacy/, Controls/933.json) — deliberately asymmetric per tier, replacing
     * a previously-guessed generic 3x3 (primary/secondary/tersier x appearance/coding/attribute)
     * grid that didn't match the real form.
     *
     * @var array<string, string>
     */
    /**
     * Labels corrected 2026-09-03 against the real Power Apps export
     * (ipc_app/app_legacy/extracted/Controls/933.json): the SharePoint column names
     * PRIMARY_PACKAGING and PRIMARY_CAPPING_BATCH_EXP were kept as-is (matching the real
     * schema), but their on-screen Text labels were overridden in the legacy app to
     * "PRIMARY APPEARANCE" and "PRIMART CODING / EMBOSS" respectively — the column names
     * never got renamed to match. Use these display labels, not the column names, or the
     * screen won't match what IPC users actually see in legacy.
     */
    public const PRIMARY_FIELDS = [
        'primary_bulk_status' => 'Primary Bulk',
        'primary_packaging_status' => 'Primary Appearance',
        'primary_capping_batch_exp_status' => 'Primary Coding / Emboss',
        'primary_na_number_status' => 'Primary NA Number',
        'primary_attribute_status' => 'Primary Attribute',
        'primary_functional_test_status' => 'Primary Functional Test',
    ];

    /** @var array<string, string> */
    public const SECONDARY_FIELDS = [
        'secondary_identity_status' => 'Secondary Identity',
        'secondary_appearance_status' => 'Secondary Appearance',
        'secondary_coding_na_status' => 'Secondary Coding / NA',
        'secondary_attribute_status' => 'Secondary Attribute',
    ];

    /** @var array<string, string> */
    public const TERSIER_FIELDS = [
        'tersier_identity_status' => 'Tersier Identity',
        'tersier_appearance_status' => 'Tersier Appearance',
        'tersier_coding_na_status' => 'Tersier Coding / NA',
    ];

    /**
     * secondary_coding_na_status is the one tri-state field (Conform / Not Conform / N/A);
     * every other checklist item is a plain two-value Conform / Not Conform.
     *
     * @return list<array{key: string, fields: array<string, string>, options: array<int, string>}>
     */
    public static function checklistGroups(): array
    {
        $conform = [self::STATUS_CONFORM, self::STATUS_NOT_CONFORM];

        return [
            ['key' => 'primary', 'fields' => self::PRIMARY_FIELDS, 'options' => $conform],
            ['key' => 'secondary', 'fields' => array_diff_key(self::SECONDARY_FIELDS, ['secondary_coding_na_status' => true]), 'options' => $conform],
            ['key' => 'secondary_coding_na', 'fields' => ['secondary_coding_na_status' => self::SECONDARY_FIELDS['secondary_coding_na_status']], 'options' => [self::STATUS_CONFORM, self::STATUS_NOT_CONFORM, self::STATUS_NA]],
            ['key' => 'tersier', 'fields' => self::TERSIER_FIELDS, 'options' => $conform],
        ];
    }

    protected $fillable = [
        'ipc_batch_id',
        'user_id',
        'primary_bulk_status',
        'primary_packaging_status',
        'primary_capping_batch_exp_status',
        'primary_na_number_status',
        'primary_attribute_status',
        'primary_functional_test_status',
        'secondary_identity_status',
        'secondary_appearance_status',
        'secondary_coding_na_status',
        'secondary_attribute_status',
        'tersier_identity_status',
        'tersier_appearance_status',
        'tersier_coding_na_status',
        'standard_weight_mb',
        'sum_weight_mb',
        'line_leader_name',
        'coding_machine',
        'remarks',
        'decision',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'standard_weight_mb' => 'decimal:4',
            'sum_weight_mb' => 'decimal:4',
            'completed_at' => 'datetime',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(IpcBatch::class, 'ipc_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
