<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingCheck extends Model
{
    public const STATUS_CONFORM = 'Conform';

    public const STATUS_NOT_CONFORM = 'Not Conform';

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
        'primary_appearance_status',
        'primary_coding_status',
        'primary_attribute_status',
        'secondary_appearance_status',
        'secondary_coding_status',
        'secondary_attribute_status',
        'tersier_appearance_status',
        'tersier_coding_status',
        'tersier_attribute_status',
        'coding_machine',
        'remarks',
        'decision',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
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
