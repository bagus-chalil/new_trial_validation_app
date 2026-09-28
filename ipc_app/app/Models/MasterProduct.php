<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fg_code',
        'product_name',
        'shelf_life_months',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'shelf_life_months' => 'integer',
        ];
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function bulkCodes()
    {
        return $this->hasMany(MasterProductBulkCode::class);
    }
}
