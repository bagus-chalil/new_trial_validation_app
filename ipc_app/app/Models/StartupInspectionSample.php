<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StartupInspectionSample extends Model
{
    public const SAMPLE_COUNT = 30;

    protected $fillable = [
        'startup_inspection_id',
        'sample_no',
        'volume_weight',
        'weight_master_box',
    ];

    protected function casts(): array
    {
        return [
            'volume_weight' => 'decimal:2',
            'weight_master_box' => 'integer',
        ];
    }

    public function startupInspection()
    {
        return $this->belongsTo(StartupInspection::class);
    }
}
