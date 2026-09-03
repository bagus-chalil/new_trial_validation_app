<?php

namespace App\Http\Controllers;

use App\Actions\StartupChecks\SaveStartupCheck;
use App\Http\Requests\SaveStartupCheckRequest;
use App\Http\Requests\UploadStartupCheckPhotoRequest;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\StartupCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StartupCheckController extends Controller
{
    /** Field labels for the three camera-only fields on this stage — see StartupCheck migration note. */
    public const PHOTO_FIELDS = ['im_number', 'color', 'temperature_setting'];

    public function edit(IpcBatch $batch): Response
    {
        $batch->load(['masterProduct', 'masterLine', 'startupCheck.user', 'startupInspection']);

        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'startup')
            ->whereIn('field_label', self::PHOTO_FIELDS)
            ->get()
            ->keyBy('field_label');

        $photoUrls = collect(self::PHOTO_FIELDS)
            ->mapWithKeys(fn (string $field) => [
                $field => $photos->has($field) ? Storage::disk('public')->url($photos[$field]->file_path) : null,
            ]);

        return Inertia::render('startup-check/edit', [
            'batch' => $batch,
            'startupCheck' => $batch->startupCheck,
            'isReadOnly' => (bool) $batch->startupCheck?->completed_at,
            'startupInspectionComplete' => (bool) $batch->startupInspection?->completed_at,
            'checklistGroups' => StartupCheck::checklistGroups(),
            'validationReportOptions' => StartupCheck::VALIDATION_REPORT_OPTIONS,
            'photoUrls' => $photoUrls,
        ]);
    }

    public function update(SaveStartupCheckRequest $request, IpcBatch $batch, SaveStartupCheck $action): RedirectResponse
    {
        abort_if($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini sudah selesai dan bersifat read-only.');

        $action->handle($batch, $request->user(), $request->validated());

        return redirect()->route('batches.show', $batch)->with('success', 'Startup Check tersimpan.');
    }

    public function uploadPhoto(UploadStartupCheckPhotoRequest $request, IpcBatch $batch, string $field): RedirectResponse
    {
        abort_unless(in_array($field, self::PHOTO_FIELDS, true), 404);
        abort_if($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini sudah selesai dan bersifat read-only.');

        $existing = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'startup')
            ->where('field_label', $field)
            ->get();

        $path = $request->file('photo')->store("ipc-attachments/{$batch->id}/startup", 'public');

        IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'startup',
            'field_label' => $field,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        foreach ($existing as $old) {
            Storage::disk('public')->delete($old->file_path);
            $old->delete();
        }

        return redirect()->route('startup-check.edit', $batch)->with('success', 'Foto tersimpan.');
    }
}
