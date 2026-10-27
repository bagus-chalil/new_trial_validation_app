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

    /** Shown wherever "Selesaikan" is refused because the Micro Check block is not complete yet. */
    public const MICRO_CHECK_REQUIRED_MESSAGE = 'Micro Check belum terisi oleh Microbiologist, jadi Finished Good belum bisa diselesaikan dan batch belum bisa lanjut ke Approval. Minta Microbiologist mengisi Micro Check terlebih dahulu.';

    protected $fillable = [
        'ipc_batch_id',
        'user_id',
        'quantity_wi',
        'masterbox',
        'no_pallet_qty',
        'quantity_sampling_aql',
        'quantity_sample_aql_cd',
        'quantity_sample_aql_md',
        'quantity_sample_aql_mnd',
        'quantity_special_inspection',
        'quantity_special_inspection_cd',
        'quantity_special_inspection_md',
        'quantity_special_inspection_mnd',
        'line_leader_name',
        'disposition',
        'remarks',
        'save_count',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_wi' => 'integer',
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

    public function revisions()
    {
        return $this->hasMany(FinishedCheckRevision::class);
    }
}
