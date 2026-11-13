<?php

namespace App\Actions\Vision;

use App\Jobs\ReadStartupPhotoJob;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Puts the Vision reads of a batch's saved Startup photos on the queue, skipping every read
 * that is already done or already waiting (idempotent: the page calls it on every photo /
 * packaging-type change). Quick OCR reads go on `vision`, the slow tube LLM read on
 * `vision-slow`; one worker serves both (vision first), so the AI server stays serial.
 */
class QueueStartupAiReads
{
    public const QUEUE = 'vision';

    public const QUEUE_SLOW = 'vision-slow';

    /** Seconds a "waiting/running" marker lives, in case a job dies without cleaning up. */
    public const TTL = 900;

    public static function cacheKey(int $batchId, int $attachmentId, string $fieldType): string
    {
        return "startup_ai:{$batchId}:{$attachmentId}:{$fieldType}";
    }

    /**
     * The reads one photo needs: [fieldType => queue]. Tube packaging adds the display-only tube
     * reads on the Primary Coding photos.
     *
     * @return array<string, string>
     */
    public static function readsFor(string $field, ?string $packagingType): array
    {
        $main = config("vision.startup_ai.fields.{$field}");
        if ($main === null) {
            return [];
        }

        $reads = [$main => self::QUEUE];

        if ($main === 'coding_print' && $packagingType === IpcBatch::PACKAGING_TUBE) {
            foreach (config('vision.startup_ai.tube_field_types', []) as $type) {
                $reads[$type] = self::QUEUE;
            }
            if ($llm = config('vision.startup_ai.tube_llm_field_type')) {
                $reads[$llm] = self::QUEUE_SLOW;
            }
        }

        return $reads;
    }

    public function handle(IpcBatch $batch, User $user, ?string $packagingType): int
    {
        $queued = 0;

        foreach (array_keys(config('vision.startup_ai.fields', [])) as $field) {
            $attachment = IpcAttachment::query()
                ->where('ipc_batch_id', $batch->id)
                ->where('stage', 'startup')
                ->where('field_label', $field)
                ->latest('id')
                ->first();
            if ($attachment === null) {
                continue;
            }

            foreach (self::readsFor($field, $packagingType) as $fieldType => $queue) {
                $done = IpcLog::query()
                    ->where('ipc_batch_id', $batch->id)
                    ->where('field_type', $fieldType)
                    ->where('image_path', $attachment->file_path)
                    ->exists();
                if ($done) {
                    continue;
                }

                // Cache::add is atomic: a second request for the same read finds the marker and skips.
                if (! Cache::add(self::cacheKey($batch->id, $attachment->id, $fieldType), 'queued', self::TTL)) {
                    continue;
                }

                ReadStartupPhotoJob::dispatch($batch->id, $attachment->id, $field, $fieldType, $user->id)->onQueue($queue);
                $queued++;
            }
        }

        return $queued;
    }
}
