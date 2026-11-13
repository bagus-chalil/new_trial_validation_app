<?php

namespace App\Jobs;

use App\Actions\Vision\QueueStartupAiReads;
use App\Actions\Vision\ReadStartupPhoto;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * One Vision read of one Startup Check photo. Runs on the dedicated `vision` / `vision-slow`
 * queues, served by a single worker process, so the (serial) AI server is never hit by more
 * than one request at a time. A failure never blocks the operator: the card just shows
 * "belum terbaca" with a retry.
 */
class ReadStartupPhotoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Must stay below DB_QUEUE_RETRY_AFTER (360s) and above vision.llm_timeout.
    public int $timeout = 200;

    public function __construct(
        public int $batchId,
        public int $attachmentId,
        public string $field,
        public string $fieldType,
        public ?int $userId,
    ) {}

    public function handle(ReadStartupPhoto $action): void
    {
        $key = QueueStartupAiReads::cacheKey($this->batchId, $this->attachmentId, $this->fieldType);

        try {
            Cache::put($key, 'running', QueueStartupAiReads::TTL);

            $batch = IpcBatch::find($this->batchId);
            $attachment = IpcAttachment::find($this->attachmentId); // gone when the photo was replaced meanwhile

            if ($batch !== null && $attachment !== null) {
                $action->handle($batch, $attachment, $this->field, $this->fieldType, $this->userId);
            }
        } finally {
            Cache::forget($key);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(QueueStartupAiReads::cacheKey($this->batchId, $this->attachmentId, $this->fieldType));
    }
}
