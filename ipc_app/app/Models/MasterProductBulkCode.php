<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterProductBulkCode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'master_product_id',
        'bulk_code',
        'no_batch',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function masterProduct()
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
