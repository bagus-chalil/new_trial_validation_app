<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpcPrintLog extends Model
{
    protected $fillable = [
        'ipc_batch_id',
        'stage',
        'printed_by_user_id',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(IpcBatch::class, 'ipc_batch_id');
    }

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }
}
