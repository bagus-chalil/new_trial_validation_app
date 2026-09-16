<?php

namespace App\Http\Controllers;

use App\Actions\FillingChecks\SaveFillingCheck;
use App\Http\Requests\SaveFillingCheckRequest;
use App\Http\Requests\UploadFillingCheckPhotoRequest;
use App\Models\FillingCheck;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FillingCheckController extends Controller
{
    /**
     * Field labels for the camera-only LargeImage columns on this stage. `color` was the
     * original field (see the "Filling Check" section in CLAUDE.md); `wo_image`/`date_bulk`/
     * `image_tube` were added 2026-09-16 per direct user request against a real legacy
     * FILLING CHECK screenshot showing WO_Image, Date_Bulk, and Image_Tube camera fields
     * alongside Color, none of which existed on this stage yet.
     */
    public const PHOTO_FIELDS = ['color', 'wo_image', 'date_bulk', 'image_tube'];

    public function edit(IpcBatch $batch): Response
    {
        abort_unless($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini belum selesai.');

        $batch->load([
            'masterProduct',
            'masterLine',
            'startupCheck',
            'startupInspection.samples',
            'fillingCheck.samples',
            'fillingCheck.revisions' => fn ($query) => $query->latest('revision_no'),
            'fillingCheck.revisions.samples',
            'fillingCheck.revisions.user',
        ]);

        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'filling')
            ->whereIn('field_label', self::PHOTO_FIELDS)
            ->latest()
            ->get()
            ->keyBy('field_label');

        $photoUrls = collect(self::PHOTO_FIELDS)
            ->mapWithKeys(fn (string $field) => [
                $field => $photos->has($field) ? Storage::disk('public')->url($photos[$field]->file_path) : null,
            ]);

        return Inertia::render('filling-check/edit', [
            'batch' => $batch,
            'fillingCheck' => $batch->fillingCheck,
            'isReadOnly' => (bool) $batch->fillingCheck?->completed_at,
            'decisions' => FillingCheck::DECISIONS,
            'photoUrls' => $photoUrls,
            'startupInspectionSamples' => $batch->startupInspection?->samples ?? [],
        ]);
    }

    public function update(SaveFillingCheckRequest $request, IpcBatch $batch, SaveFillingCheck $action): RedirectResponse
    {
        abort_unless($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini belum selesai.');
        abort_if($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini sudah selesai dan bersifat read-only.');

        $batch->load('startupInspection');
        $data = $request->validated();
        $action->handle($batch, $request->user(), $data);

        if ($data['finalize']) {
            return redirect()->route('batches.show', $batch)->with('success', 'Filling Check tersimpan.');
        }

        return redirect()->route('filling-check.edit', $batch)->with('success', 'Progress tersimpan.');
    }

    public function uploadPhoto(UploadFillingCheckPhotoRequest $request, IpcBatch $batch, string $field): RedirectResponse
    {
        abort_unless(in_array($field, self::PHOTO_FIELDS, true), 404);
        abort_unless($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini belum selesai.');
        abort_if($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini sudah selesai dan bersifat read-only.');

        $existing = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'filling')
            ->where('field_label', $field)
            ->get();

        $path = $request->file('photo')->store("ipc-attachments/{$batch->id}/filling", 'public');

        IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'filling',
            'field_label' => $field,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        foreach ($existing as $old) {
            Storage::disk('public')->delete($old->file_path);
            $old->delete();
        }

        return redirect()->route('filling-check.edit', $batch)->with('success', 'Foto tersimpan.');
    }
}
