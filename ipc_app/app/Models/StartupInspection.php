<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StartupInspection extends Model
{
    protected $fillable = [
        'ipc_batch_id',
        'user_id',
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

    public function items()
    {
        return $this->hasMany(StartupInspectionItem::class);
    }

    public function samples()
    {
        return $this->hasMany(StartupInspectionSample::class);
    }

    public function testResults()
    {
        return $this->hasMany(StartupInspectionTestResult::class);
    }
}
