<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StartupInspectionTestResult extends Model
{
    protected $fillable = [
        'startup_inspection_id',
        'master_test_type_id',
        'is_performed',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'is_performed' => 'boolean',
        ];
    }

    public function startupInspection()
    {
        return $this->belongsTo(StartupInspection::class);
    }

    public function testType()
    {
        return $this->belongsTo(MasterTestType::class, 'master_test_type_id');
    }
}
