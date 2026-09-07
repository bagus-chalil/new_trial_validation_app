<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingCheckRevisionPhoto extends Model
{
    protected $fillable = [
        'packing_check_revision_id',
        'field_label',
        'ipc_attachment_id',
        'file_path',
    ];

    public function revision()
    {
        return $this->belongsTo(PackingCheckRevision::class, 'packing_check_revision_id');
    }

    public function attachment()
    {
        return $this->belongsTo(IpcAttachment::class, 'ipc_attachment_id');
    }
}
