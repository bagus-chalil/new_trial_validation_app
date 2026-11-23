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
        });
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
