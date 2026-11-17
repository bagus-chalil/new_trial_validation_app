<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpcAiFieldFeedback extends Model
{
    protected $table = 'ipc_ai_field_feedback';

    protected $fillable = [
        'ipc_batch_id', 'ipc_log_id', 'field_key', 'field_type', 'ai_value', 'ai_trusted',
        'final_value', 'source', 'outcome', 'packaging_type',
    ];

    protected function casts(): array
    {
        return ['ai_trusted' => 'boolean'];
    }

    public function batch()
    {
        return $this->belongsTo(IpcBatch::class, 'ipc_batch_id');
    }
}
