<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StartupInspectionSample extends Model
{
    protected $fillable = [
        'startup_inspection_id',
        'sample_no',
        'volume',
        'weight',
        'weight_master_box',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'decimal:4',
            'weight' => 'decimal:4',
            'weight_master_box' => 'decimal:4',
        ];
    }

    public function startupInspection()
    {
        return $this->belongsTo(StartupInspection::class);
    }
}
