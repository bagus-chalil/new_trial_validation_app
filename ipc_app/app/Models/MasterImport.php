<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterImport extends Model
{
    public const TYPE_PRODUCTS = 'master_products';

    public const TYPE_LINES = 'master_lines';

    public const TYPES = [self::TYPE_PRODUCTS, self::TYPE_LINES];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_COMMIT_QUEUED = 'commit_queued';

    public const STATUS_COMMITTING = 'committing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses in which a background job is (or should be) working on this import. */
    public const RUNNING_STATUSES = [self::STATUS_QUEUED, self::STATUS_VALIDATING, self::STATUS_COMMIT_QUEUED, self::STATUS_COMMITTING];

    /** Statuses a user can still resume from the import dialog. */
    public const OPEN_STATUSES = [...self::RUNNING_STATUSES, self::STATUS_VALIDATED];

    public const FINAL_STATUSES = [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED];

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'cancel_requested',
        'original_filename',
        'file_path',
        'total_rows',
        'processed_rows',
        'valid_rows',
        'error_rows',
        'warning_rows',
        'preview',
        'result',
        'error_message',
        'started_at',
        'validated_at',
        'committed_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'cancel_requested' => 'boolean',
            'preview' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'validated_at' => 'datetime',
            'committed_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function issues()
    {
        return $this->hasMany(MasterImportIssue::class);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, self::RUNNING_STATUSES, true);
    }
}
