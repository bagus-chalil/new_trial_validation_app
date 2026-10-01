<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpcBatchBulkCode extends Model
{
    protected $fillable = [
        'ipc_batch_id',
        'master_product_bulk_code_id',
        'bulk_code',
    ];

    public function batch()
    {
        return $this->belongsTo(IpcBatch::class, 'ipc_batch_id');
    }

    public function masterBulkCode()
    {
        return $this->belongsTo(MasterProductBulkCode::class, 'master_product_bulk_code_id');
    }
}
