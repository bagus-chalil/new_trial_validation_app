<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FillingCheckRevisionSample extends Model
{
    protected $fillable = [
        'filling_check_revision_id',
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

    public function revision()
    {
        return $this->belongsTo(FillingCheckRevision::class, 'filling_check_revision_id');
    }
}
