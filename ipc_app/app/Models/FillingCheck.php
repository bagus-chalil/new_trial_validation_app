<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FillingCheck extends Model
{
    public const DECISION_PASSED = 'Passed';

    public const DECISION_HOLD = 'Hold';

    public const DECISION_REJECT = 'Reject';

    public const DECISIONS = [
        self::DECISION_PASSED,
        self::DECISION_HOLD,
        self::DECISION_REJECT,
    ];

    /**
     * Display labels for FillingCheckController::PHOTO_FIELDS, in on-screen order — the PDF
     * report reads these instead of hardcoding its own copy, which had drifted ("Identity Bulk
     * Bulk" / "Image" instead of the form's "Identity Bulk" / "Appearance"). Must stay in sync
     * with PHOTO_FIELDS in resources/js/pages/filling-check/edit.tsx.
     */
    public const PHOTO_LABELS = [
        'color' => 'Color',
        'wo_image' => 'WI Image',
        'date_bulk' => 'Identity Bulk',
        'image_tube' => 'Appearance',
    ];

    protected $fillable = [
        'ipc_batch_id',
        'user_id',
        'sample_bulk_odor_status',
        'sample_leakage_test_status',
        'average_weight',
        'remarks',
        'decision',
        'save_count',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'average_weight' => 'decimal:2',
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

    public function samples()
    {
        return $this->hasMany(FillingCheckSample::class);
    }

    public function revisions()
    {
        return $this->hasMany(FillingCheckRevision::class);
    }
}
