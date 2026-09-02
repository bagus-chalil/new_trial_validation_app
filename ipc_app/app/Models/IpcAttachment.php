<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpcAttachment extends Model
{
    protected $fillable = [
        'ipc_batch_id',
        'stage',
        'field_label',
        'file_path',
        'uploaded_by',
    ];

    public function batch()
    {
        return $this->belongsTo(IpcBatch::class, 'ipc_batch_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
