<?php

namespace App\Http\Controllers;

use App\Actions\FinishedChecks\SaveFinishedCheck;
use App\Http\Requests\SaveFinishedCheckRequest;
use App\Http\Requests\UploadFinishedCheckPhotoRequest;
use App\Models\FinishedCheck;
use App\Models\FinishedCheckSample;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FinishedCheckController extends Controller
{
    /**
     * WI_NUMBER/EXP_DATE/COLOR are pure photo (LargeImage) fields in the legacy schema — see the
     * finished_checks migration note — routed through ipc_attachments like every other stage's
     * camera-only fields (StartupCheck::PHOTO_FIELDS, PackingCheckController::PHOTO_FIELDS).
     */
    public const PHOTO_FIELDS = ['wi_number', 'exp_date', 'color'];

    public function edit(IpcBatch $batch): Response
    {
        abort_unless($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini belum selesai.');

        $batch->load(['masterProduct', 'masterLine', 'finishedCheck.user', 'finishedCheck.samples']);

        $samples = $batch->finishedCheck
            ? $batch->finishedCheck->samples->keyBy('parameter_key')
            : collect();

        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'finished')
            ->whereIn('field_label', self::PHOTO_FIELDS)
            ->get()
            ->keyBy('field_label');

        $photoUrls = collect(self::PHOTO_FIELDS)
            ->mapWithKeys(fn (string $field) => [
                $field => $photos->has($field) ? Storage::disk('public')->url($photos[$field]->file_path) : null,
            ]);

        return Inertia::render('finished-check/edit', [
            'batch' => $batch,
            'finishedCheck' => $batch->finishedCheck,
            'samples' => $samples,
            'isReadOnly' => (bool) $batch->finishedCheck?->completed_at,
            'sampleGroups' => FinishedCheckSample::sampleGroups(),
            'dispositions' => FinishedCheck::DISPOSITIONS,
            'photoUrls' => $photoUrls,
        ]);
    }

    public function update(SaveFinishedCheckRequest $request, IpcBatch $batch, SaveFinishedCheck $action): RedirectResponse
    {
        abort_unless($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini belum selesai.');
        abort_if($batch->finishedCheck?->completed_at, 403, 'Finished Check untuk batch ini sudah selesai dan bersifat read-only.');

        $data = $request->validated();
        $action->handle($batch, $request->user(), $data);

        if ($data['finalize'] ?? false) {
            return redirect()->route('batches.show', $batch)->with('success', 'Finished Check tersimpan.');
        }

        return redirect()->route('finished-check.edit', $batch)->with('success', 'Progress tersimpan.');
    }

    public function uploadPhoto(UploadFinishedCheckPhotoRequest $request, IpcBatch $batch, string $field): RedirectResponse
    {
        abort_unless(in_array($field, self::PHOTO_FIELDS, true), 404);
        abort_unless($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini belum selesai.');
        abort_if($batch->finishedCheck?->completed_at, 403, 'Finished Check untuk batch ini sudah selesai dan bersifat read-only.');

        $existing = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'finished')
            ->where('field_label', $field)
            ->get();

        $path = $request->file('photo')->store("ipc-attachments/{$batch->id}/finished", 'public');

        IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'finished',
            'field_label' => $field,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        foreach ($existing as $old) {
            Storage::disk('public')->delete($old->file_path);
            $old->delete();
        }

        return redirect()->route('finished-check.edit', $batch)->with('success', 'Foto tersimpan.');
    }
}
