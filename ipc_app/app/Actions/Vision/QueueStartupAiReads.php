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
 *
 * Primary Coding with `coding_formula`: ONE read per batch carrying photo 1 + the optional photo 2
 * (`file2`). A read counts as done only for the exact photo pair it was made with, so a 2nd photo
 * uploaded later (or a replaced one) queues a fresh read of both photos together.
 */
class QueueStartupAiReads
{
    public const QUEUE = 'vision';

    public const QUEUE_SLOW = 'vision-slow';

    public const CODING_FORMULA = 'coding_formula';

    /** Seconds a "waiting/running" marker lives, in case a job dies without cleaning up. */
    public const TTL = 900;

    public static function cacheKey(int $batchId, int $attachmentId, string $fieldType, ?int $secondAttachmentId = null): string
    {
        $suffix = $secondAttachmentId !== null ? "+{$secondAttachmentId}" : '';

        return "startup_ai:{$batchId}:{$attachmentId}:{$fieldType}{$suffix}";
    }

    /**
     * The reads one photo needs: [fieldType => queue]. Tube packaging adds the display-only tube
     * reads on the Primary Coding photos (old path; with coding_formula only when
     * vision.startup_ai.legacy_tube_reads is on).
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

        $legacyTube = $main === 'coding_print'
            || ($main === self::CODING_FORMULA && config('vision.startup_ai.legacy_tube_reads'));

        if ($legacyTube && $packagingType === IpcBatch::PACKAGING_TUBE) {
            foreach (config('vision.startup_ai.tube_field_types', []) as $type) {
                $reads[$type] = self::QUEUE;
            }
            if ($llm = config('vision.startup_ai.tube_llm_field_type')) {
                $reads[$llm] = self::QUEUE_SLOW;
            }
        }

        return $reads;
    }

    public static function latestPhoto(IpcBatch $batch, string $field): ?IpcAttachment
    {
        return IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'startup')
            ->where('field_label', $field)
            ->latest('id')
            ->first();
    }

    /** The photo sent as `file2` with this read: the 2nd Primary Coding photo, for coding_formula only. */
    public static function secondPhotoFor(IpcBatch $batch, string $field, string $fieldType): ?IpcAttachment
    {
        if ($fieldType !== self::CODING_FORMULA || $field !== 'coding_primary') {
            return null;
        }

        return self::latestPhoto($batch, 'coding_primary_2');
    }

    /** The log of this read for exactly this photo (pair), or null when it still has to be read. */
    public static function findLog(IpcBatch $batch, IpcAttachment $attachment, string $fieldType, ?IpcAttachment $second = null): ?IpcLog
    {
        $logs = IpcLog::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('field_type', $fieldType)
            ->where('image_path', $attachment->file_path)
            ->latest('id');

        if ($fieldType !== self::CODING_FORMULA) {
            return $logs->first();
        }

        $wanted = $second?->file_path;

        return $logs->limit(20)->get()->first(fn (IpcLog $log) => ($log->comparison['image_path_2'] ?? null) === $wanted);
    }

    public function handle(IpcBatch $batch, User $user, ?string $packagingType): int
    {
        $queued = 0;

        foreach (array_keys(config('vision.startup_ai.fields', [])) as $field) {
            $reads = self::readsFor($field, $packagingType);
            if ($reads === []) {
                continue;
            }

            $attachment = self::latestPhoto($batch, $field);
            if ($attachment === null) {
                continue;
            }

            foreach ($reads as $fieldType => $queue) {
                $second = self::secondPhotoFor($batch, $field, $fieldType);

                if (self::findLog($batch, $attachment, $fieldType, $second) !== null) {
                    continue;
                }

                // Cache::add is atomic: a second request for the same read finds the marker and skips.
                if (! Cache::add(self::cacheKey($batch->id, $attachment->id, $fieldType, $second?->id), 'queued', self::TTL)) {
                    continue;
                }

                ReadStartupPhotoJob::dispatch($batch->id, $attachment->id, $field, $fieldType, $user->id, $second?->id)->onQueue($queue);
                $queued++;
            }
        }

        return $queued;
    }
}
