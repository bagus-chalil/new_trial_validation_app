<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FillingCheckSample extends Model
{
    protected $fillable = [
        'filling_check_id',
        'sample_no',
        'weight_value',
        'weight_result',
    ];

    protected function casts(): array
    {
        return [
            'weight_value' => 'decimal:4',
        ];
    }

    public function fillingCheck()
    {
        return $this->belongsTo(FillingCheck::class);
    }
}
