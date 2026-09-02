<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StartupInspectionTestResult extends Model
{
    public const RESULT_PASS = 'Pass';

    public const RESULT_FAIL = 'Fail';

    protected $fillable = [
        'startup_inspection_id',
        'master_test_type_id',
        'result',
        'remark',
    ];

    public function startupInspection()
    {
        return $this->belongsTo(StartupInspection::class);
    }

    public function testType()
    {
        return $this->belongsTo(MasterTestType::class, 'master_test_type_id');
    }
}
