<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MicroCheckRevision extends Model
{
    protected $fillable = [
        'micro_check_id',
        'revision_no',
        'user_id',
        'total_plate_count_result',
        'total_plate_count_std',
        'yeast_mold_result',
        'yeast_mold_std',
        'pathogen_result',
        'pathogen_std',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
