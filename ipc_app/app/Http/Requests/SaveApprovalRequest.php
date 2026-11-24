<?php

namespace App\Http\Requests;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\IpcReportDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveApprovalRequest extends FormRequest
{
    /** Which approval level this decision is for (1 = QC Verifikator, 2 = Team Leader / Supervisor). */
    public function level(): int
    {
        return (int) $this->input('level') === IpcApproval::LEVEL_2 ? IpcApproval::LEVEL_2 : IpcApproval::LEVEL_1;
    }

    public function authorize(): bool
    {
        return $this->level() === IpcApproval::LEVEL_2
            ? Gate::allows('approve-ipc-l2')
            : Gate::allows('approve-ipc-l1');
    }

    protected function prepareForValidation(): void
    {
        // A single-stage report can only send back that one stage, so the form doesn't ask — and
        // always sends an empty `revise_stages: []`, which must not count as "already chosen".
        if ($this->isRevision() && ! $this->isCombinedReport()) {
            $this->merge(['revise_stages' => [(string) $this->route('stage')]]);
        }
    }

    public function rules(): array
    {
        $needsNote = in_array($this->input('decision'), [IpcApproval::DECISION_REJECTED, IpcApproval::DECISION_REVISION], true);

        return [
            'level' => ['nullable', Rule::in([IpcApproval::LEVEL_1, IpcApproval::LEVEL_2])],
            // Level 2 can only Approve or Reject; "Minta Revisi" (reopening QC's checks) is Level 1's.
            'decision' => ['required', Rule::in($this->level() === IpcApproval::LEVEL_2 ? IpcApproval::L2_DECISIONS : IpcApproval::DECISIONS)],
            'remarks' => [$needsNote ? 'required' : 'nullable', 'string'],
            // The form always sends `revise_stages: []`; only a Revision needs at least one stage,
            // so Approved / Rejected must accept the empty array.
            'revise_stages' => $this->isRevision() ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'revise_stages.*' => ['string', Rule::in($this->revisableStages())],
            // Optional: stage => TH Progress round numbers that are wrong (see RequestApprovalRevision).
            'revise_rounds' => ['nullable', 'array'],
            'revise_rounds.*' => ['array'],
            'revise_rounds.*.*' => ['integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'remarks.required' => 'Tuliskan catatan agar QC tahu apa yang harus diperbaiki / alasan penolakan.',
            'revise_stages.required' => 'Pilih minimal satu bagian laporan yang perlu direvisi.',
            'revise_stages.min' => 'Pilih minimal satu bagian laporan yang perlu direvisi.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $batch = $this->route('batch');

            // Once printing started, the report is already issued — correcting it is not a
            // revision of an open approval any more.
            if ($this->isRevision() && $batch instanceof IpcBatch && $batch->current_stage !== IpcBatch::STAGE_APPROVAL) {
                $validator->errors()->add('decision', 'Revisi hanya bisa diminta selama batch masih di tahap Approval.');
            }

            if ($this->isRevision() && $batch instanceof IpcBatch) {
                $this->validateRevisionRounds($validator, $batch);
            }
        });
    }

    /** A flagged round must belong to a stage that is being sent back and must really exist. */
    private function validateRevisionRounds(Validator $validator, IpcBatch $batch): void
    {
        $stages = (array) $this->input('revise_stages', []);

        foreach ((array) $this->input('revise_rounds', []) as $stage => $rounds) {
            if (! in_array($stage, [IpcBatch::STAGE_FILLING, IpcBatch::STAGE_PACKING, IpcBatch::STAGE_FINISHED], true) || ! in_array($stage, $stages, true)) {
                $validator->errors()->add('revise_rounds', 'TH Progress hanya bisa dipilih untuk bagian yang ikut direvisi.');

                continue;
            }

            $check = match ($stage) {
                IpcBatch::STAGE_FILLING => $batch->fillingCheck,
                IpcBatch::STAGE_PACKING => $batch->packingCheck,
                default => $batch->finishedCheck,
            };
            $existing = $check?->revisions()->pluck('revision_no')->map(fn ($n) => (int) $n)->all() ?? [];

            foreach ((array) $rounds as $no) {
                if (! in_array((int) $no, $existing, true)) {
                    $validator->errors()->add('revise_rounds', "TH Progress #{$no} tidak ditemukan pada tahap {$stage}.");
                }
            }
        }
    }

    private function isRevision(): bool
    {
        return $this->input('decision') === IpcApproval::DECISION_REVISION;
    }

    private function isCombinedReport(): bool
    {
        return $this->route('stage') === IpcReportDocument::KEY_FILLING_PACKING_FG;
    }

    /** @return list<string> */
    private function revisableStages(): array
    {
        $batch = $this->route('batch');

        if ($this->isCombinedReport() && $batch instanceof IpcBatch) {
            return IpcApproval::combinedStagesFor($batch);
        }

        return [(string) $this->route('stage')];
    }
}
