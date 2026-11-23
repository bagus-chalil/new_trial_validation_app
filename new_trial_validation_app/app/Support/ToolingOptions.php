<?php

namespace App\Support;

/**
 * Stored vocabularies of Check Tooling, identical to the legacy Project
 * Tracking constants (VALID_TOOLING_* in its db.php) so imported rows and new
 * rows share one set of raw values; the frontend translates them for display.
 */
final class ToolingOptions
{
    public const STATUS_WAITING_RNI_SAMPLE = 'Menunggu RNI & Sampel';

    public const STATUS_WAITING_RNI = 'Menunggu Review RNI';

    public const STATUS_WAITING_SAMPLE = 'Menunggu Sampel';

    public const STATUS_PIE_CHECK = 'Cek Tooling PI';

    public const STATUS_PRODUCTION = 'Review Production';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_HOLD = 'Revisi / Hold';

    public const STATUS_REJECTED = 'Ditolak';

    /** Display / filter order. Legacy 'Draft' and 'Submitted' are folded into the derived statuses on import. */
    public const STATUSES = [
        self::STATUS_WAITING_RNI_SAMPLE,
        self::STATUS_WAITING_RNI,
        self::STATUS_WAITING_SAMPLE,
        self::STATUS_PIE_CHECK,
        self::STATUS_PRODUCTION,
        self::STATUS_APPROVED,
        self::STATUS_HOLD,
        self::STATUS_REJECTED,
    ];

    /** Statuses that end the flow: nothing can be signed or edited any more. */
    public const FINAL_STATUSES = [self::STATUS_APPROVED, self::STATUS_REJECTED];

    public const SAMPLE_STATUSES = ['Menunggu Sampel', 'Sampel Sedang Dikirim', 'Sampel Diterima', 'Selesai Dicek'];

    public const PRODUCT_TYPES = ['skin_care', 'make_up', 'other'];

    public const TOOL_STATUSES = ['OK', 'NOK', 'Need New', 'N/A'];

    public const INVEST_VALUES = ['YES', 'NO'];

    public const RISK_LEVELS = ['High risk', 'Medium risk', 'Low risk'];

    /** Signature boxes in signing order. */
    public const ROLES = ['prni', 'rni', 'pi', 'production'];

    /** Default tool rows offered to PIE (same as the old app's DEF_TOOLING_TOOLS). */
    public const DEFAULT_TOOLS = ['Ring hot air', 'Cutter', 'Nozzle', 'Holder'];

    /** PIE's check SLA after the sample arrives, in working days (old app: 5WD). */
    public const PIE_SLA_WORKING_DAYS = 5;

    public const DOC_CODE = 'FR.PRNI.002.00';
}
