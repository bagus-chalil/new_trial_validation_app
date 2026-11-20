<?php

namespace App\Http\Controllers;

use App\Services\Vision\LabelBulkScan;
use App\Services\Vision\VisionClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Scan Label Bulk" on Batch Baru: the operator photographs the yellow Label Bulk BEFORE the batch exists.
 * The photo is parked under ipc-attachments/pending and the Vision read is cached under a `scan_id`; when the
 * batch is created (IpcBatchController::store) both are attached to it as the Startup Check Label Bulk photo +
 * its AI read, so Startup needs no second photo. Advisory only: the form may fill empty fields from a trusted
 * read, but the operator can always overwrite them or drop the photo.
 */
class BatchLabelScanController extends Controller
{
    public const CACHE_PREFIX = 'batch_label_scan:';

    public const CACHE_TTL_HOURS = 6;

    private const PENDING_DIR = 'ipc-attachments/pending';

    public function scan(Request $request, VisionClient $client, LabelBulkScan $scan): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192']]);

        // One LLM read can take 25-80s plus queueing on the (serial) AI server.
        set_time_limit((int) config('vision.llm_timeout') + 30);

        $photo = $request->file('photo');
        $scanId = (string) Str::uuid();
        $path = $photo->storeAs(self::PENDING_DIR.'/'.$request->user()->id, $scanId.'.'.($photo->guessExtension() ?: 'jpg'), 'public');
        $this->pruneOldPending();

        $entry = ['user_id' => $request->user()->id, 'path' => $path, 'vision' => null, 'read' => null];
        $read = null;

        if (config('vision.startup_ai.enabled')) {
            $result = $client->analyze($photo->get(), $photo->getClientOriginalName() ?: 'label-bulk.jpg', 'label_bulk', $scanId);
            $read = $scan->interpret($result);
            // Kept only when the service answered: an ERROR read is simply redone on Startup Check.
            $entry['vision'] = $result->status === 'ERROR' ? null : ['status' => $result->status, 'engine_used' => $result->engineUsed, 'confidence' => $result->confidence, 'processing_time_ms' => $result->processingTimeMs, 'error_reason' => $result->errorReason, 'raw' => $result->raw];
            $entry['read'] = $read;
        }

        Cache::put(self::CACHE_PREFIX.$scanId, $entry, now()->addHours(self::CACHE_TTL_HOURS));

        return response()->json([
            'scan_id' => $scanId,
            'photo_url' => Storage::disk('public')->url($path),
            'ai_enabled' => (bool) config('vision.startup_ai.enabled'),
            'read' => $read,
        ]);
    }

    /** Drops a parked photo the operator discarded ("Hapus foto"). Only the owner's own scan. */
    public function discard(Request $request, string $scanId): JsonResponse
    {
        $entry = Cache::get(self::CACHE_PREFIX.$scanId);
        if (is_array($entry) && ($entry['user_id'] ?? null) === $request->user()->id) {
            Storage::disk('public')->delete($entry['path']);
            Cache::forget(self::CACHE_PREFIX.$scanId);
        }

        return response()->json(['ok' => true]);
    }

    /** Photos of scans that were never turned into a batch don't pile up. */
    private function pruneOldPending(): void
    {
        $disk = Storage::disk('public');
        $limit = now()->subDays(2)->getTimestamp();

        foreach ($disk->allFiles(self::PENDING_DIR) as $file) {
            if ($disk->lastModified($file) < $limit) {
                $disk->delete($file);
            }
        }
    }

    /** @return array<string, mixed>|null the cached scan when it belongs to this user and its photo still exists */
    public static function claim(int $userId, ?string $scanId): ?array
    {
        if ($scanId === null || $scanId === '') {
            return null;
        }

        $entry = Cache::get(self::CACHE_PREFIX.$scanId);

        return is_array($entry) && ($entry['user_id'] ?? null) === $userId && Storage::disk('public')->exists($entry['path']) ? $entry : null;
    }

    public static function forget(string $scanId): void
    {
        Cache::forget(self::CACHE_PREFIX.$scanId);
    }
}
