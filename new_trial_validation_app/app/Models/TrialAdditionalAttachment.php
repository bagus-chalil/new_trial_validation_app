<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A supplementary PDF/image attached to a trial by its drafter or one of its
 * reviewers, independent of the trial's status — see the
 * create_trial_additional_attachments_table migration. Physical file lives
 * on the private `local` disk at "{STORAGE_DIR}/{trial_id}/{file_name}".
 *
 * @property int $id
 * @property int $trial_id
 * @property string $original_name
 * @property string $file_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $description
 * @property int|null $uploaded_by_user_id
 * @property string|null $uploaded_by_name
 * @property string|null $uploader_role
 * @property Carbon|null $created_at
 */
#[Fillable(['trial_id', 'original_name', 'file_name', 'mime_type', 'size_bytes', 'description', 'uploaded_by_user_id', 'uploaded_by_name', 'uploader_role'])]
class TrialAdditionalAttachment extends Model
{
    public const STORAGE_DIR = 'trial-additional-attachments';

    public const MAX_PER_TRIAL = 10;

    public const MAX_SIZE_KB = 10 * 1024;

    /**
     * @var array<string, string>
     */
    public const ALLOWED_MIME_TO_EXTENSION = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    protected $table = 'trial_additional_attachments';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Trial, $this>
     */
    public function trial(): BelongsTo
    {
        return $this->belongsTo(Trial::class, 'trial_id');
    }

    public function storagePath(): string
    {
        return self::STORAGE_DIR.'/'.$this->trial_id.'/'.$this->file_name;
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
