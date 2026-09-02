<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StartupCheck extends Model
{
    public const STATUS_AVAILABLE = 'Available';

    public const STATUS_NOT_AVAILABLE = 'Not Available';

    public const STATUS_CONFORM = 'Conform';

    public const STATUS_NOT_CONFORM = 'Not Conform';

    public const STATUS_MATCH_WITH_BOM = 'Match With BOM';

    public const STATUS_NOT_MATCH_WITH_BOM = 'Not Match With BOM';

    public const STATUS_BULK_RELEASE = 'Bulk Release';

    public const STATUS_BULK_NOT_YET_RELEASE = 'Bulk Not Yet Release';

    public const STATUS_COMPLETE = 'Complete';

    public const STATUS_NOT_YET_COMPLETE = 'Not Yet Complete';

    /**
     * Available / Not Available checklist items — confirmed against the real Power Apps
     * export 2026-09-02 (ipc_app/app_legacy/, Controls/4.json): the 3 header items plus all
     * 5 machine checks (machine checks were previously mis-grouped as Conform/Not Conform).
     *
     * @var array<string, string>
     */
    public const AVAILABILITY_FIELDS = [
        'product_standard_status' => 'Product Standard',
        'sample_challenge_test_status' => 'Sample Challenge Test',
        'wi_im_match_status' => 'WI / IM Already Match',
        'machine_vision_status' => 'Machine Vision System',
        'machine_weigher_status' => 'Machine Weigher Check',
        'machine_roller_status' => 'Machine Roller / Sheetmask',
        'machine_load_cell_status' => 'Machine Load Cell',
        'machine_balance_status' => 'Machine Balance',
    ];

    /**
     * Conform / Not Conform checklist items — includes 5 items that were entirely missing
     * from the original guessed schema (found in the real export).
     *
     * @var array<string, string>
     */
    public const CONFORM_FIELDS = [
        'scan_bpom_status' => 'Scan Number NA with BPOM Mobile',
        'sample_30pcs_appearance_status' => '30 PCS Start Up Sample — Appearance, Coding, Attribute',
        'sample_30pcs_vacuum_status' => '30 PCS Start Up Sample — Vacuum Test',
        'functional_test_status' => 'Functional Test (5 PCS Sample)',
        'standard_weight_masterbox_status' => 'Standard Weight Masterbox',
    ];

    /** @var array<string, string> */
    public const PM_BOM_MATCH_FIELDS = [
        'pm_bom_match_status' => 'All PM Already Match With BOM',
    ];

    /** @var array<string, string> */
    public const BULK_STATUS_FIELDS = [
        'bulk_status_status' => 'Bulk Status',
    ];

    /** @var array<string, string> */
    public const IDENTITY_LINE_BOARD_FIELDS = [
        'identity_line_board_status' => 'Identity Line Board',
    ];

    /**
     * `validation_report_status` is deliberately excluded from every group above: the real
     * legacy field is a genuine SharePoint Choice dropdown (not a two-value button group),
     * and its allowed values aren't embedded anywhere in the Power Apps export — only a
     * required free-text field can be built until the real choice list is obtained some
     * other way (e.g. asking IPC/QA for the SharePoint list's Choices).
     *
     * @return list<array{key: string, fields: array<string, string>, options: array<int, string>}>
     */
    public static function checklistGroups(): array
    {
        return [
            ['key' => 'availability', 'fields' => self::AVAILABILITY_FIELDS, 'options' => [self::STATUS_AVAILABLE, self::STATUS_NOT_AVAILABLE]],
            ['key' => 'conform', 'fields' => self::CONFORM_FIELDS, 'options' => [self::STATUS_CONFORM, self::STATUS_NOT_CONFORM]],
            ['key' => 'pm_bom_match', 'fields' => self::PM_BOM_MATCH_FIELDS, 'options' => [self::STATUS_MATCH_WITH_BOM, self::STATUS_NOT_MATCH_WITH_BOM]],
            ['key' => 'bulk_status', 'fields' => self::BULK_STATUS_FIELDS, 'options' => [self::STATUS_BULK_RELEASE, self::STATUS_BULK_NOT_YET_RELEASE]],
            ['key' => 'identity_line_board', 'fields' => self::IDENTITY_LINE_BOARD_FIELDS, 'options' => [self::STATUS_COMPLETE, self::STATUS_NOT_YET_COMPLETE]],
        ];
    }

    protected $fillable = [
        'ipc_batch_id',
        'user_id',
        'product_standard_status',
        'sample_challenge_test_status',
        'wi_im_match_status',
        'pm_bom_match_status',
        'bulk_status_status',
        'machine_vision_status',
        'machine_weigher_status',
        'machine_roller_status',
        'machine_load_cell_status',
        'machine_balance_status',
        'validation_report_status',
        'identity_line_board_status',
        'scan_bpom_status',
        'sample_30pcs_appearance_status',
        'sample_30pcs_vacuum_status',
        'functional_test_status',
        'standard_weight_masterbox_status',
        'filling_range_min',
        'filling_range_max',
        'density',
        'heating',
        'line_leader_name',
        'operator_name',
        'remarks',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filling_range_min' => 'decimal:2',
            'filling_range_max' => 'decimal:2',
            'density' => 'decimal:4',
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

    public function bottleWeights()
    {
        return $this->hasMany(StartupBottleWeight::class);
    }
}
