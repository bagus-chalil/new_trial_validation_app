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

    protected $fillable = [
        'ipc_batch_id',
        'user_id',
        'sample_bulk_odor_status',
        'sample_leakage_test_status',
        'standard_weight_and_volume',
        'average_weight',
        'line_leader_name',
        'remarks',
        'decision',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'average_weight' => 'decimal:4',
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
}
