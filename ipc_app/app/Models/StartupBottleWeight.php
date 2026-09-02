<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StartupBottleWeight extends Model
{
    protected $fillable = [
        'startup_check_id',
        'sample_no',
        'weight_value',
    ];

    protected function casts(): array
    {
        return [
            'weight_value' => 'decimal:4',
        ];
    }

    public function startupCheck()
    {
        return $this->belongsTo(StartupCheck::class);
    }
}
