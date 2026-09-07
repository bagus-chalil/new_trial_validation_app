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

        $batch->load([
            'masterProduct',
            'masterLine',
            'packingCheck.user',
            'packingCheck.revisions' => fn ($query) => $query->latest('revision_no'),
            'packingCheck.revisions.user',
        ]);

        // Ordered ascending so keyBy() below keeps the *latest* row per field — packing photos
        // now accumulate one row per TH_PROGRESS round (see uploadPhoto()) rather than
        // overwriting, so the "current" photo shown on this edit page is the most recent upload,
        // not necessarily the only one.
        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'packing')
            ->whereIn('field_label', self::PHOTO_FIELDS)
            ->orderBy('id')
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
            'standardWeightMb' => SavePackingCheck::standardWeightMbFor($batch),
        ]);
    }

    public function update(SavePackingCheckRequest $request, IpcBatch $batch, SavePackingCheck $action): RedirectResponse
    {
        abort_unless($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini belum selesai.');
        abort_if($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini sudah selesai dan bersifat read-only.');

        $data = $request->validated();
        $action->handle($batch, $request->user(), $data);

        if ($data['finalize']) {
            return redirect()->route('finished-check.edit', $batch)->with('success', 'Packing Check tersimpan.');
        }

        return redirect()->route('packing-check.edit', $batch)->with('success', 'Progress tersimpan.');
    }

    public function uploadPhoto(UploadPackingCheckPhotoRequest $request, IpcBatch $batch, string $field): RedirectResponse
    {
        abort_unless(in_array($field, self::PHOTO_FIELDS, true), 404);
        abort_unless($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini belum selesai.');
        abort_if($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini sudah selesai dan bersifat read-only.');

        // Deliberately does NOT delete/replace the previous row for this field, unlike every
        // other stage's photo upload — packing goes through repeatable TH_PROGRESS rounds
        // (see SavePackingCheck), and the printed report needs to show which photo belonged to
        // which round. So every upload just appends a new row; "current" (for this edit page
        // and for the finalize-requires-a-photo check) is simply the latest one per field.
        // SavePackingCheck::handle() snapshots whichever row is latest at save time into
        // PackingCheckRevisionPhoto, which is what the report actually reads per round.
        $path = $request->file('photo')->store("ipc-attachments/{$batch->id}/packing", 'public');

        IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'packing',
            'field_label' => $field,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('packing-check.edit', $batch)->with('success', 'Foto tersimpan.');
    }
}
