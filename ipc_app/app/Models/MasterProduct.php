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
        // Vision Service coding formula (catalog key / template / JSON array). NULL = "auto".
        'coding_formula',
        'is_active',
    ];

    /** What to send as the Vision Service `coding_formula` form field: the SKU's formula, or "auto". */
    public function codingFormulaForVision(): string
    {
        $formula = trim((string) $this->coding_formula);

        return $formula === '' ? 'auto' : $formula;
    }

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
