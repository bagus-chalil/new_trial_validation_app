<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinishedCheckRevisionSample extends Model
{
    protected $fillable = [
        'finished_check_revision_id',
        'parameter_key',
        'ac',
        'cd',
        'md',
        'mnd',
        'remark',
    ];

    public function revision()
    {
        return $this->belongsTo(FinishedCheckRevision::class, 'finished_check_revision_id');
    }
}
