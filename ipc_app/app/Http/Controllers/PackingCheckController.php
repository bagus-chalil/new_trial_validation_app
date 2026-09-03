<?php

namespace App\Http\Controllers;

use App\Actions\PackingChecks\SavePackingCheck;
use App\Http\Requests\SavePackingCheckRequest;
use App\Http\Requests\UploadPackingCheckPhotoRequest;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\PackingCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PackingCheckController extends Controller
{
    /**
     * Field labels for the five camera-only LargeImage columns confirmed against the real
     * Power Apps export 2026-09-03 (ipc_app/app_legacy/, Controls/933.json/DataSources.json):
     * PALLETISASI, COLOR, PRIMARY_CODING_BATCH_EXP, TERSIER_CODING_BATCH,
     * SECONDARY_CODING_BATCH_EXP. Each is a distinct column from the Conform/Not Conform
     * checklist fields above — e.g. PRIMARY_CODING_BATCH_EXP (photo) is separate from
     * PRIMARY_CAPPING_BATCH_EXP (the "Primary Coding / Emboss" toggle). A sixth LargeImage
     * column, SHIPPER_LABEL, exists in the schema but has no control anywhere on this screen
     * (dead column) — deliberately not built here.
     */
    public const PHOTO_FIELDS = ['palletisasi', 'color', 'primary_coding_batch_exp', 'tersier_coding_batch', 'secondary_coding_batch_exp'];

    public function edit(IpcBatch $batch): Response
    {
        abort_unless($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini belum selesai.');

        $batch->load(['masterProduct', 'masterLine', 'packingCheck.user']);

        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'packing')
            ->whereIn('field_label', self::PHOTO_FIELDS)
            ->get()
            ->keyBy('field_label');

        $photoUrls = collect(self::PHOTO_FIELDS)
            ->mapWithKeys(fn (string $field) => [
                $field => $photos->has($field) ? Storage::disk('public')->url($photos[$field]->file_path) : null,
            ]);

        return Inertia::render('packing-check/edit', [
            'batch' => $batch,
            'packingCheck' => $batch->packingCheck,
            'isReadOnly' => (bool) $batch->packingCheck?->completed_at,
            'checklistGroups' => PackingCheck::checklistGroups(),
            'decisions' => PackingCheck::DECISIONS,
            'photoUrls' => $photoUrls,
        ]);
    }

    public function update(SavePackingCheckRequest $request, IpcBatch $batch, SavePackingCheck $action): RedirectResponse
    {
        abort_unless($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini belum selesai.');
        abort_if($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini sudah selesai dan bersifat read-only.');

        $action->handle($batch, $request->user(), $request->validated());

        return redirect()->route('batches.show', $batch)->with('success', 'Packing Check tersimpan.');
    }

    public function uploadPhoto(UploadPackingCheckPhotoRequest $request, IpcBatch $batch, string $field): RedirectResponse
    {
        abort_unless(in_array($field, self::PHOTO_FIELDS, true), 404);
        abort_unless($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini belum selesai.');
        abort_if($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini sudah selesai dan bersifat read-only.');

        $existing = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'packing')
            ->where('field_label', $field)
            ->get();

        $path = $request->file('photo')->store("ipc-attachments/{$batch->id}/packing", 'public');

        IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'packing',
            'field_label' => $field,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        foreach ($existing as $old) {
            Storage::disk('public')->delete($old->file_path);
            $old->delete();
        }

        return redirect()->route('packing-check.edit', $batch)->with('success', 'Foto tersimpan.');
    }
}
