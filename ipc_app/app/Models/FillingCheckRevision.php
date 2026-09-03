<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FillingCheckRevision extends Model
{
    protected $fillable = [
        'filling_check_id',
        'revision_no',
        'finalize',
        'sample_bulk_odor_status',
        'sample_leakage_test_status',
        'remarks',
        'decision',
        'average_weight',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'finalize' => 'boolean',
            'average_weight' => 'decimal:4',
        ];
    }

    public function fillingCheck()
    {
        return $this->belongsTo(FillingCheck::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function samples()
    {
        return $this->hasMany(FillingCheckRevisionSample::class);
    }
}
