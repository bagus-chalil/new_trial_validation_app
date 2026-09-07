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
    /** Field labels for the four camera-only fields on this stage — see StartupCheck migration note. */
    public const PHOTO_FIELDS = ['im_number', 'color', 'temperature_setting', 'coding'];

    /** Fields that support multiple photos (accumulate instead of replace). */
    private const MULTI_PHOTO_FIELDS = ['temperature_setting'];

    public function edit(IpcBatch $batch): Response
    {
        $batch->load(['masterProduct', 'masterLine', 'startupCheck.user', 'startupInspection']);

        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'startup')
            ->whereIn('field_label', self::PHOTO_FIELDS)
            ->orderBy('id')
            ->get()
            ->groupBy('field_label');

        $disk = Storage::disk('public');

        // Single-photo fields → string|null, multi-photo fields → array of {id, url}
        $photoUrls = collect(self::PHOTO_FIELDS)->mapWithKeys(function (string $field) use ($photos, $disk) {
            $rows = $photos->get($field, collect());
            if (in_array($field, self::MULTI_PHOTO_FIELDS, true)) {
                return [$field => $rows->map(fn ($a) => ['id' => $a->id, 'url' => $disk->url($a->file_path)])->values()];
            }

            return [$field => $rows->isNotEmpty() ? $disk->url($rows->last()->file_path) : null];
        });

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

        $path = $request->file('photo')->store("ipc-attachments/{$batch->id}/startup", 'public');

        IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'startup',
            'field_label' => $field,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        // Single-photo fields: delete previous after storing the new one
        if (! in_array($field, self::MULTI_PHOTO_FIELDS, true)) {
            IpcAttachment::query()
                ->where('ipc_batch_id', $batch->id)
                ->where('stage', 'startup')
                ->where('field_label', $field)
                ->where('file_path', '!=', $path)
                ->get()
                ->each(function ($old) {
                    Storage::disk('public')->delete($old->file_path);
                    $old->delete();
                });
        }

        return redirect()->route('startup-check.edit', $batch)->with('success', 'Foto tersimpan.');
    }

    public function deletePhoto(IpcBatch $batch, IpcAttachment $attachment): RedirectResponse
    {
        abort_if($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini sudah selesai dan bersifat read-only.');
        abort_unless(
            $attachment->ipc_batch_id === $batch->id && $attachment->stage === 'startup' && in_array($attachment->field_label, self::MULTI_PHOTO_FIELDS, true),
            404,
        );

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return redirect()->route('startup-check.edit', $batch)->with('success', 'Foto dihapus.');
    }
}
