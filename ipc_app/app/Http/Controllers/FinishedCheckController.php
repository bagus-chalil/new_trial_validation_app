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
use Illuminate\Support\Facades\Gate;
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

    /**
     * WI Number is the one field this stage keeps accumulating instead of replacing — per direct
     * user request, it also stays uploadable after Finished Check is finalized (see the
     * completed_at check in uploadPhoto()), unlike exp_date/color which lock like everything else.
     */
    public const MULTI_PHOTO_FIELDS = ['wi_number'];

    public const MAX_PHOTOS_PER_FIELD = ['wi_number' => 4];

    public function edit(IpcBatch $batch): Response
    {
        abort_unless($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini belum selesai.');

        $batch->load([
            'masterProduct',
            'masterLine',
            'packingCheck',
            'finishedCheck.user',
            'finishedCheck.samples',
            'finishedCheck.revisions' => fn ($query) => $query->latest('revision_no'),
            'finishedCheck.revisions.user',
        ]);

        $samples = $batch->finishedCheck
            ? $batch->finishedCheck->samples->keyBy('parameter_key')
            : collect();

        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'finished')
            ->whereIn('field_label', self::PHOTO_FIELDS)
            ->orderBy('id')
            ->get()
            ->groupBy('field_label');

        $disk = Storage::disk('public');

        // Single-photo fields → string|null, multi-photo fields (wi_number) → array of {id, url}
        $photoUrls = collect(self::PHOTO_FIELDS)->mapWithKeys(function (string $field) use ($photos, $disk) {
            $rows = $photos->get($field, collect());
            if (in_array($field, self::MULTI_PHOTO_FIELDS, true)) {
                return [$field => $rows->map(fn ($a) => ['id' => $a->id, 'url' => $disk->url($a->file_path)])->values()];
            }

            return [$field => $rows->isNotEmpty() ? $disk->url($rows->last()->file_path) : null];
        });

        return Inertia::render('finished-check/edit', [
            'batch' => $batch,
            'finishedCheck' => $batch->finishedCheck,
            'samples' => $samples,
            'isReadOnly' => ! Gate::allows('update', $batch) || (bool) $batch->finishedCheck?->completed_at,
            'sampleGroups' => FinishedCheckSample::sampleGroups(),
            'dispositions' => FinishedCheck::DISPOSITIONS,
            'photoUrls' => $photoUrls,
            'lineleaderName' => $batch->packingCheck?->line_leader_name,
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

        $isMulti = in_array($field, self::MULTI_PHOTO_FIELDS, true);

        // Every field locks once Finished Check is finalized — except WI Number (see the
        // MULTI_PHOTO_FIELDS doc comment above), which stays uploadable up to its cap below.
        abort_if(! $isMulti && $batch->finishedCheck?->completed_at, 403, 'Finished Check untuk batch ini sudah selesai dan bersifat read-only.');

        // Exp Date locks earlier than that: per direct user request, it can only be captured on
        // the very first TH Progress round (before the form has ever been saved, draft or final) —
        // once $batch->finishedCheck exists at all, no more uploads/replacements for this field.
        abort_if($field === 'exp_date' && $batch->finishedCheck !== null, 403, 'Exp Date hanya bisa diunggah pada TH Progress pertama, sebelum disimpan.');

        $existing = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'finished')
            ->where('field_label', $field)
            ->get();

        if ($isMulti && $existing->count() >= self::MAX_PHOTOS_PER_FIELD[$field]) {
            return redirect()->route('finished-check.edit', $batch)
                ->withErrors(['photo_'.$field => 'Maksimal '.self::MAX_PHOTOS_PER_FIELD[$field].' foto untuk field ini.']);
        }

        $path = $request->file('photo')->store("ipc-attachments/{$batch->id}/finished", 'public');

        IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'finished',
            'field_label' => $field,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        // Single-photo fields: delete previous after storing the new one. Multi-photo fields
        // (wi_number) accumulate instead.
        if (! $isMulti) {
            foreach ($existing as $old) {
                Storage::disk('public')->delete($old->file_path);
                $old->delete();
            }
        }

        return redirect()->route('finished-check.edit', $batch)->with('success', 'Foto tersimpan.');
    }

    public function deletePhoto(IpcBatch $batch, IpcAttachment $attachment): RedirectResponse
    {
        // Only a MULTI_PHOTO_FIELDS field (wi_number) can ever reach this action — exp_date/color
        // attachments are replaced in place by uploadPhoto(), never deleted standalone. No
        // completed_at lock here either, matching wi_number's stay-editable-after-finalize rule.
        abort_unless(
            $attachment->ipc_batch_id === $batch->id && $attachment->stage === 'finished' && in_array($attachment->field_label, self::MULTI_PHOTO_FIELDS, true),
            404,
        );

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return redirect()->route('finished-check.edit', $batch)->with('success', 'Foto dihapus.');
    }
}
