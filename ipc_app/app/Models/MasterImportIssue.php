<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterImportIssue extends Model
{
    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

    public $timestamps = false;

    protected $fillable = [
        'master_import_id',
        'row_number',
        'severity',
        'message',
        'values',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
        ];
    }

    public function masterImport()
    {
        return $this->belongsTo(MasterImport::class);
    }
}
