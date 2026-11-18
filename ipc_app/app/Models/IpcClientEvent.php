<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One field issue (browser- or server-side). See the migration for the "resolved" semantics.
 *
 * @property int $id
 * @property string $fingerprint
 * @property string $kind
 * @property string $message
 */
class IpcClientEvent extends Model
{
    public const KINDS = ['js_error', 'promise', 'request_failed', 'slow_request', 'camera', 'upload', 'user_report', 'server_error'];

    public const UPDATED_AT = null;

    protected $table = 'ipc_client_events';

    protected $fillable = [
        'fingerprint', 'kind', 'message', 'detail', 'url', 'user_id', 'ipc_batch_id', 'user_agent',
        'browser', 'os', 'device', 'online', 'net_type', 'app_version', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['online' => 'boolean', 'occurred_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
