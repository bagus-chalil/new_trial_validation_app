<?php

namespace App\Actions\Vision;

use App\Http\Controllers\BatchLabelScanController;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcLog;
use App\Models\User;
use App\Services\Vision\VisionEvaluation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns a Label Bulk photo scanned on Batch Baru into the batch's Startup Check Label Bulk photo (`date_bulk`)
 * plus its AI read (one ipc_logs row, same shape as ReadStartupPhoto writes), so Startup Check finds the photo
 * already there (it is a required field) and the "Rekomendasi AI" card does not read it a second time.
 * `entered` is what the operator ended up with on the form, kept in `comparison` so the AI-vs-operator gap
 * (the accuracy measurement) survives. Never throws for a vanished photo: no scan = nothing attached.
 */
class AttachScannedLabelBulk
{
    /** @param  array<string, mixed>  $entered  final form values: no_batch, master_product_id, bulk_codes */
    public function handle(IpcBatch $batch, User $user, ?string $scanId, array $entered): ?IpcAttachment
    {
        $entry = BatchLabelScanController::claim($user->id, $scanId);
        if ($entry === null) {
            return null;
        }

        $disk = Storage::disk('public');
        $target = "ipc-attachments/{$batch->id}/startup/".basename($entry['path']);
        if (! $disk->move($entry['path'], $target)) {
            return null;
        }

        $attachment = IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'startup',
            'field_label' => 'date_bulk',
            'file_path' => $target,
            'uploaded_by' => $user->id,
        ]);

        $vision = $entry['vision'] ?? null;
        if (is_array($vision)) {
            $read = $entry['read'] ?? [];
            IpcLog::create([
                'request_id' => (string) Str::uuid(),
                'ipc_batch_id' => $batch->id,
                'stage' => IpcBatch::STAGE_STARTUP,
                'field_type' => 'label_bulk',
                'image_path' => $target,
                'vision_status' => $vision['status'],
                'engine_used' => $vision['engine_used'] ?? null,
                'confidence' => $vision['confidence'] ?? null,
                'error_reason' => $vision['error_reason'] ?? null,
                'processing_time_ms' => $vision['processing_time_ms'] ?? null,
                'vision_response' => $vision['raw'] ?: null,
                'decision' => VisionEvaluation::REVIEW,
                'decision_reason' => 'Dibaca otomatis saat pembuatan batch; perbandingan dengan form dihitung saat ditampilkan.',
                'comparison' => [
                    'field' => 'date_bulk',
                    'queued' => true,
                    'from_batch_create' => true,
                    'ai' => ['no_batch' => $read['no_batch'] ?? null, 'kode_bulk' => $read['kode_bulk'] ?? null, 'mixing_date' => $read['mixing_date'] ?? null],
                    'entered' => $entered,
                ],
                'created_by' => $user->id,
            ]);
        }

        BatchLabelScanController::forget($scanId);

        return $attachment;
    }
}
