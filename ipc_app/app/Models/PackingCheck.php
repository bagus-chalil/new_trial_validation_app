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
     * Item list, order, and labels follow the paper form FR.QAC.193.00 Rev 0 ("Laporan
     * Pemeriksaan In Process Control Filling & Packing", user-shared 2026-10-01) — 17 items
     * across the three tiers, plus Weight of MB (sum_weight_mb) as Tersier's 6th row. This
     * supersedes the 13-item Power Apps layout (ipc_app/app_legacy/, Controls/933.json), which
     * had merged Capping/Sealing into Coding Batch & EXP on Primary and Coding Batch & EXP into
     * Coding NA on Secondary/Tersier, and had no Shipper Label row. Column names predate this and
     * are kept as-is (primary_packaging_status = "Packaging", primary_capping_batch_exp_status =
     * "Coding Batch & EXP", primary_na_number_status = "Coding NA"). Labels carry no tier prefix:
     * every screen and the report render them under their tier's heading, like the paper form.
     *
     * @var array<string, string>
     */
    public const PRIMARY_FIELDS = [
        'primary_bulk_status' => 'Bulk',
        'primary_packaging_status' => 'Packaging',
        'primary_capping_sealing_status' => 'Capping / Sealing',
        'primary_capping_batch_exp_status' => 'Coding Batch & EXP',
        'primary_na_number_status' => 'Coding NA',
        'primary_attribute_status' => 'Attribute',
        'primary_functional_test_status' => 'Functional Test',
    ];

    /** @var array<string, string> */
    public const SECONDARY_FIELDS = [
        'secondary_identity_status' => 'Identity',
        'secondary_appearance_status' => 'Appearance',
        'secondary_coding_batch_exp_status' => 'Coding Batch & EXP',
        'secondary_coding_na_status' => 'Coding NA',
        'secondary_attribute_status' => 'Attribute',
    ];

    /** @var array<string, string> */
    public const TERSIER_FIELDS = [
        'tersier_identity_status' => 'Identity',
        'tersier_appearance_status' => 'Appearance',
        'tersier_coding_batch_exp_status' => 'Coding Batch & EXP',
        'tersier_coding_na_status' => 'Coding NA',
        'tersier_shipper_label_status' => 'Shipper Label',
    ];

    /** "Data Timbang" header field on the paper form. */
    public const WEIGHING_DATA_OPTIONS = ['Ada', 'Tidak Ada'];

    /**
     * Every checklist item is tri-state Conform / Not Conform / N/A since 2026-09-03, per direct
     * user request — not every item applies to every product (a product with no secondary
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
     * Defect-severity labels (ZD = Zero Defect, C = Critical, M = Major, m = Minor) printed next
     * to each checklist row on the report, copied from the paper form FR.QAC.193.00 Rev 0. Purely
     * informational (confirmed with the user 2026-09-07 — "istilah C/M/m/ZD cmn label aja, isinya
     * tetap conform") — they never affect validation or the stored decision.
     *
     * @var array<string, string>
     */
    public const SEVERITY_LABELS = [
        'primary_bulk_status' => 'C',
        'primary_packaging_status' => 'M',
        'primary_capping_sealing_status' => 'C',
        'primary_capping_batch_exp_status' => 'ZD',
        'primary_na_number_status' => 'ZD',
        'primary_attribute_status' => 'ZD',
        'primary_functional_test_status' => 'C',
        'secondary_identity_status' => 'C',
        'secondary_appearance_status' => 'M',
        'secondary_coding_batch_exp_status' => 'ZD',
        'secondary_coding_na_status' => 'ZD',
        'secondary_attribute_status' => 'ZD',
        'tersier_identity_status' => 'C',
        'tersier_appearance_status' => 'm',
        'tersier_coding_batch_exp_status' => 'ZD',
        'tersier_coding_na_status' => 'ZD',
        'tersier_shipper_label_status' => 'C',
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
        'secondary_coding_batch_exp_status' => 'secondary_coding_batch_exp',
        'tersier_coding_batch_exp_status' => 'tersier_coding_batch',
    ];

    protected $fillable = [
        'ipc_batch_id',
        'user_id',
        'primary_bulk_status',
        'primary_packaging_status',
        'primary_capping_sealing_status',
        'primary_capping_batch_exp_status',
        'primary_na_number_status',
        'primary_attribute_status',
        'primary_functional_test_status',
        'secondary_identity_status',
        'secondary_appearance_status',
        'secondary_coding_batch_exp_status',
        'secondary_coding_na_status',
        'secondary_attribute_status',
        'tersier_identity_status',
        'tersier_appearance_status',
        'tersier_coding_batch_exp_status',
        'tersier_coding_na_status',
        'tersier_shipper_label_status',
        'standard_weight_mb',
        'sum_weight_mb',
        'line_leader_name',
        'coding_machine',
        'weighing_data',
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
