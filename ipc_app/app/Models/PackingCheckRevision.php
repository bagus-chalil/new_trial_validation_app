<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingCheckRevision extends Model
{
    protected $fillable = [
        'packing_check_id',
        'revision_no',
        'finalize',
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
        'remarks',
        'decision',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'finalize' => 'boolean',
            'standard_weight_mb' => 'decimal:4',
            'sum_weight_mb' => 'decimal:4',
        ];
    }

    public function packingCheck()
    {
        return $this->belongsTo(PackingCheck::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
