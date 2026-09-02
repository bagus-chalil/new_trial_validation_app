<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpcBatch extends Model
{
    use SoftDeletes;

    public const STAGE_STARTUP = 'startup';

    public const STAGE_FILLING = 'filling';

    public const STAGE_PACKING = 'packing';

    public const STAGE_FINISHED = 'finished';

    public const STAGE_APPROVAL = 'approval';

    public const STAGE_PRINT = 'print';

    public const STAGE_COMPLETED = 'completed';

    public const STAGES = [
        self::STAGE_STARTUP,
        self::STAGE_FILLING,
        self::STAGE_PACKING,
        self::STAGE_FINISHED,
        self::STAGE_APPROVAL,
        self::STAGE_PRINT,
        self::STAGE_COMPLETED,
    ];

    protected $fillable = [
        'master_product_id',
        'no_batch',
        'master_line_id',
        'created_by',
        'current_stage',
    ];

    public function masterProduct()
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function masterLine()
    {
        return $this->belongsTo(MasterLine::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function startupCheck()
    {
        return $this->hasOne(StartupCheck::class);
    }

    public function startupInspection()
    {
        return $this->hasOne(StartupInspection::class);
    }

    public function fillingCheck()
    {
        return $this->hasOne(FillingCheck::class);
    }

    public function packingCheck()
    {
        return $this->hasOne(PackingCheck::class);
    }

    public function finishedCheck()
    {
        return $this->hasOne(FinishedCheck::class);
    }

    public function approvals()
    {
        return $this->hasMany(IpcApproval::class);
    }

    public function printLogs()
    {
        return $this->hasMany(IpcPrintLog::class);
    }

    public function attachments()
    {
        return $this->hasMany(IpcAttachment::class);
    }
}
