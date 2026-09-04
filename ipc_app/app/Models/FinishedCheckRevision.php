<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinishedCheckRevision extends Model
{
    protected $fillable = [
        'finished_check_id',
        'revision_no',
        'finalize',
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
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'finalize' => 'boolean',
            'quantity_wi' => 'decimal:2',
            'masterbox' => 'decimal:2',
            'no_pallet_qty' => 'decimal:2',
        ];
    }

    public function finishedCheck()
    {
        return $this->belongsTo(FinishedCheck::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function samples()
    {
        return $this->hasMany(FinishedCheckRevisionSample::class);
    }
}
