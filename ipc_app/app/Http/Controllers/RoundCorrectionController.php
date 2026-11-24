<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\CorrectCheckRound;
use App\Http\Requests\SaveRoundCorrectionRequest;
use App\Http\Requests\UploadPackingCheckPhotoRequest;
use App\Models\FillingCheck;
use App\Models\FinishedCheck;
use App\Models\FinishedCheckSample;
use App\Models\IpcApproval;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcRoundCorrection;
use App\Models\PackingCheck;
use App\Models\PackingCheckRevisionPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where QC fixes the TH Progress round an approver flagged in "Minta Revisi". See
 * CorrectCheckRound for how the correction and its audit trail are stored.
 */
class RoundCorrectionController extends Controller
{
    /**
     * QC asks to correct a saved round itself - for a revision requested without round-level
     * flagging (older requests) or when another round turns out wrong. Needs an open revision.
     */
    public function store(Request $request, IpcBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'in:'.implode(',', [IpcBatch::STAGE_FILLING, IpcBatch::STAGE_PACKING, IpcBatch::STAGE_FINISHED])],
            'revision_no' => ['required', 'integer', 'min:1'],
        ]);

        abort_unless($batch->hasOpenRevision(), 403, 'Tidak ada permintaan revisi yang terbuka untuk batch ini.');
        abort_if($batch->stageIsCompleted($data['stage']), 403, 'Tahap ini tidak sedang dibuka untuk revisi.');
        abort_if(CorrectCheckRound::revision($batch, $data['stage'], (int) $data['revision_no']) === null, 404);

        $approval = $batch->approvals()->where('decision', IpcApproval::DECISION_REVISION)->latest('updated_at')->first();

        $correction = IpcRoundCorrection::firstOrCreate(
            ['ipc_batch_id' => $batch->id, 'stage' => $data['stage'], 'revision_no' => (int) $data['revision_no'], 'status' => IpcRoundCorrection::STATUS_OPEN],
            ['requested_by' => $request->user()->id, 'request_note' => $approval?->remarks, 'requested_at' => now()],
        );

        return redirect()->route('corrections.edit', [$batch, $correction]);
    }

    public function edit(IpcBatch $batch, IpcRoundCorrection $correction): Response
    {
        $this->guard($batch, $correction);

        $stage = $correction->stage;
        $snapshot = CorrectCheckRound::snapshot($batch, $stage, (int) $correction->revision_no);
        abort_if($snapshot === null, 404);

        $batch->load(['masterProduct', 'masterLine']);
        $correction->load('requester:id,name');

        return Inertia::render('corrections/edit', [
            'batch' => $batch,
            'correction' => $correction,
            'stage' => $stage,
            'stageLabel' => IpcApproval::STAGE_LABELS[$stage] ?? $stage,
            'values' => $snapshot['revision'],
            'samples' => $snapshot['samples'],
            'checklistGroups' => $stage === IpcBatch::STAGE_PACKING ? PackingCheck::checklistGroups() : [],
            'sampleGroups' => $stage === IpcBatch::STAGE_FINISHED ? FinishedCheckSample::sampleGroups() : [],
            'decisions' => $stage === IpcBatch::STAGE_FILLING ? FillingCheck::DECISIONS : PackingCheck::DECISIONS,
            'dispositions' => FinishedCheck::DISPOSITIONS,
            'photos' => collect($snapshot['photos'])->map(fn (string $path) => Storage::disk('public')->url($path))->all(),
            'photoFields' => $stage === IpcBatch::STAGE_PACKING ? PackingCheckController::PHOTO_FIELDS : [],
        ]);
    }

    public function update(SaveRoundCorrectionRequest $request, IpcBatch $batch, IpcRoundCorrection $correction, CorrectCheckRound $action): RedirectResponse
    {
        $this->guard($batch, $correction);

        $action->handle($batch, $request->user(), $correction, $request->validated(), $request->validated('correction_reason'));

        return redirect()->route($this->stageRoute($correction->stage), $batch)->with('success', "Koreksi TH Progress #{$correction->revision_no} tersimpan dan tercatat di audit.");
    }

    /**
     * Replaces one photo of the round being corrected (Packing keeps a photo per field per
     * round). Camera-only on the client like every other photo. The first change stores the
     * "before" snapshot, so the audit still shows the original photo.
     */
    public function photo(UploadPackingCheckPhotoRequest $request, IpcBatch $batch, IpcRoundCorrection $correction, string $field): RedirectResponse
    {
        $this->guard($batch, $correction);
        abort_unless($correction->stage === IpcBatch::STAGE_PACKING, 404);
        abort_unless(in_array($field, PackingCheckController::PHOTO_FIELDS, true), 404);

        $revision = CorrectCheckRound::revision($batch, IpcBatch::STAGE_PACKING, (int) $correction->revision_no);
        abort_if($revision === null, 404);

        if ($correction->before_data === null) {
            $correction->update(['before_data' => CorrectCheckRound::snapshot($batch, IpcBatch::STAGE_PACKING, (int) $correction->revision_no)]);
        }

        $path = $request->file('photo')->store("ipc-attachments/{$batch->id}/packing", 'public');

        $attachment = IpcAttachment::create([
            'ipc_batch_id' => $batch->id,
            'stage' => 'packing',
            'field_label' => $field,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        PackingCheckRevisionPhoto::updateOrCreate(
            ['packing_check_revision_id' => $revision->id, 'field_label' => $field],
            ['ipc_attachment_id' => $attachment->id, 'file_path' => $path],
        );

        return redirect()->route('corrections.edit', [$batch, $correction])->with('success', 'Foto diganti untuk ronde ini.');
    }

    /** Drops a correction request made by mistake; a photo already replaced is put back. */
    public function cancel(Request $request, IpcBatch $batch, IpcRoundCorrection $correction): RedirectResponse
    {
        abort_unless(Gate::allows('update', $batch) || Gate::allows('approve-ipc-l1'), 403);
        $this->guard($batch, $correction);

        if ($correction->stage === IpcBatch::STAGE_PACKING && $correction->before_data !== null) {
            $revision = CorrectCheckRound::revision($batch, IpcBatch::STAGE_PACKING, (int) $correction->revision_no);
            $original = $correction->before_data['photos'] ?? [];
            if ($revision) {
                foreach (PackingCheckController::PHOTO_FIELDS as $field) {
                    if (isset($original[$field])) {
                        $revision->photos()->where('field_label', $field)->update(['file_path' => $original[$field]]);
                    } else {
                        $revision->photos()->where('field_label', $field)->delete();
                    }
                }
            }
        }

        $correction->update([
            'status' => IpcRoundCorrection::STATUS_CANCELLED,
            'corrected_by' => $request->user()->id,
            'corrected_at' => now(),
        ]);

        return redirect()->route($this->stageRoute($correction->stage), $batch)->with('success', 'Permintaan koreksi dibatalkan.');
    }

    private function stageRoute(string $stage): string
    {
        return match ($stage) {
            IpcBatch::STAGE_FILLING => 'filling-check.edit',
            IpcBatch::STAGE_PACKING => 'packing-check.edit',
            default => 'finished-check.edit',
        };
    }

    private function guard(IpcBatch $batch, IpcRoundCorrection $correction): void
    {
        abort_unless($correction->ipc_batch_id === $batch->id, 404);
        abort_unless($correction->revision_no !== null, 404);
        abort_unless($correction->isOpen(), 403, 'Koreksi ini sudah dikerjakan.');
        // Corrections only exist while the approver's revision request is open.
        abort_unless($batch->hasOpenRevision(), 403, 'Tidak ada permintaan revisi yang terbuka untuk batch ini.');
    }
}
