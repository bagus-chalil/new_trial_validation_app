<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinishedCheck extends Model
{
    public const DISPOSITION_ACCEPTED = 'Accepted';

    public const DISPOSITION_ACCEPTED_WITH_REMARKS = 'Accepted With Remarks';

    public const DISPOSITION_REJECTED = 'Rejected';

    public const DISPOSITIONS = [
        self::DISPOSITION_ACCEPTED,
        self::DISPOSITION_ACCEPTED_WITH_REMARKS,
        self::DISPOSITION_REJECTED,
    ];

    protected $fillable = [
        'ipc_batch_id',
        'user_id',
        'wi_number',
        'exp_date',
        'quantity_wi',
        'masterbox',
        'no_pallet_qty',
        'quantity_sampling_aql',
        'quantity_special_inspection',
        'disposition',
        'remarks',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'exp_date' => 'date',
            'quantity_wi' => 'decimal:2',
            'masterbox' => 'decimal:2',
            'no_pallet_qty' => 'decimal:2',
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
        return $this->hasMany(FinishedCheckSample::class);
    }
}
