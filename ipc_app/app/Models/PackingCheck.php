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
     * Every checklist item is tri-state Conform / Not Conform / N/A since 2026-09-03, per direct
     * user request — not all 13 items apply to every product (a product with no secondary
     * packaging still had to be marked Not Conform before, which reads as a real defect).
     * Legacy only offered N/A on secondary_coding_na_status, which is why that field used to sit
     * in its own group; now that every group shares one vocabulary it lives back in Secondary
     * where the real column order puts it.
     *
     * @return list<array{key: string, fields: array<string, string>, options: array<int, string>}>
     */
    public static function checklistGroups(): array
    {
        $options = [self::STATUS_CONFORM, self::STATUS_NOT_CONFORM, self::STATUS_NA];

        return [
            ['key' => 'primary', 'fields' => self::PRIMARY_FIELDS, 'options' => $options],
            ['key' => 'secondary', 'fields' => self::SECONDARY_FIELDS, 'options' => $options],
            ['key' => 'tersier', 'fields' => self::TERSIER_FIELDS, 'options' => $options],
        ];
    }

    /**
     * Defect-severity labels (ZD = Zero Defect, C = Critical, M = Major, m = Minor) shown next
     * to each checklist row on the printed report, matching the legacy Excel-style "In Process
     * Control Inspection Report — Filling & Packing" form the user shared as a reference. These
     * are purely informational labels printed alongside the real Conform/Not Conform/N/A answer
     * (confirmed with the user 2026-09-07 — "istilah C/M/m/ZD cmn label aja, isinya tetap
     * conform") — they never affect validation or the stored decision. Best-effort mapped from
     * the reference photo onto this app's own (deliberately normalized, not 1:1 with legacy's
     * per-tier item count) field list — e.g. primary_capping_batch_exp_status merges legacy's
     * separate "Capping/Sealing" (C) and "Coding Batch & EXP" (ZD) items into one field, so ZD
     * was picked as the closer match ("Coding / Emboss" reads closer to a coding/marking check).
     *
     * @var array<string, string>
     */
    public const SEVERITY_LABELS = [
        'primary_bulk_status' => 'C',
        'primary_packaging_status' => 'M',
        'primary_capping_batch_exp_status' => 'ZD',
        'primary_na_number_status' => 'ZD',
        'primary_attribute_status' => 'ZD',
        'primary_functional_test_status' => 'C',
        'secondary_identity_status' => 'C',
        'secondary_appearance_status' => 'M',
        'secondary_coding_na_status' => 'ZD',
        'secondary_attribute_status' => 'ZD',
        'tersier_identity_status' => 'C',
        'tersier_appearance_status' => 'm',
        'tersier_coding_na_status' => 'ZD',
    ];

    /**
     * Photo fields (see BuildsIpcReportPayloads::PHOTOS_BY_STAGE['packing']) that belong next to
     * a specific checklist row rather than in the general stage-level photo gallery — matched by
     * field-name correlation with this app's own photo field keys (not a legacy-numbering guess).
     * 'palletisasi' and 'color' have no matching checklist row and stay in the general gallery.
     *
     * @var array<string, string>
     */
    public const PHOTO_FIELD_BY_CHECKLIST_FIELD = [
        'primary_capping_batch_exp_status' => 'primary_coding_batch_exp',
        'secondary_coding_na_status' => 'secondary_coding_batch_exp',
        'tersier_coding_na_status' => 'tersier_coding_batch',
    ];

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
        'save_count',
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

    public function revisions()
    {
        return $this->hasMany(PackingCheckRevision::class);
    }
}
