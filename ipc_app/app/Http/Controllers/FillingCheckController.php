<?php

namespace App\Http\Controllers;

use App\Actions\FillingChecks\SaveFillingCheck;
use App\Http\Requests\SaveFillingCheckRequest;
use App\Http\Requests\UploadFillingColorPhotoRequest;
use App\Models\FillingCheck;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FillingCheckController extends Controller
{
    public function edit(IpcBatch $batch): Response
    {
        abort_unless($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini belum selesai.');

        $batch->load([
            'masterProduct',
            'masterLine',
            'startupCheck',
            'fillingCheck.samples',
            'fillingCheck.revisions' => fn ($query) => $query->latest('revision_no'),
            'fillingCheck.revisions.samples',
            'fillingCheck.revisions.user',
        ]);

        $colorPhoto = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'filling')
            ->where('field_label', 'color')
            ->latest()
            ->first();

        return Inertia::render('filling-check/edit', [
            'batch' => $batch,
            'fillingCheck' => $batch->fillingCheck,
            'isReadOnly' => (bool) $batch->fillingCheck?->completed_at,
            'decisions' => FillingCheck::DECISIONS,
            'colorPhotoUrl' => $colorPhoto ? Storage::disk('public')->url($colorPhoto->file_path) : null,
        ]);
    }

    public function update(SaveFillingCheckRequest $request, IpcBatch $batch, SaveFillingCheck $action): RedirectResponse
    {
        abort_unless($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini belum selesai.');
        abort_if($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini sudah selesai dan bersifat read-only.');

        $data = $request->validated();
        $action->handle($batch, $request->user(), $data);

        if ($data['finalize']) {
            return redirect()->route('batches.show', $batch)->with('success', 'Filling Check tersimpan.');
        }

        return redirect()->route('filling-check.edit', $batch)->with('success', 'Progress tersimpan.');
    }

    public function uploadColorPhoto(UploadFillingColorPhotoRequest $request, IpcBatch $batch): RedirectResponse
    {
        abort_unless($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini belum selesai.');
        abort_if($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini sudah selesai dan bersifat read-only.');

        $existing = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', 'filling')
            ->where('field_label', 'color')
            ->get();

        $path = $request->file('photo')->store("ipc-attachments/{$batch->id}/filling", 'public');

        IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'filling',
            'field_label' => 'color',
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        foreach ($existing as $old) {
            Storage::disk('public')->delete($old->file_path);
            $old->delete();
        }

        return redirect()->route('filling-check.edit', $batch)->with('success', 'Foto warna tersimpan.');
    }
}
