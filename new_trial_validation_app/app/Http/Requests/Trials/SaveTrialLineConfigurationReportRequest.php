<?php

namespace App\Http\Requests\Trials;

use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validates a Line Configuration Report save. Deliberately authorized
 * differently from every other wizard-step FormRequest in this app (which
 * all gate on `update`, i.e. the trial's own edit-rights window): this form
 * has no lock tied to the *trial's* status, so it's gated purely on
 * `manage-line-configuration-report` (PROD review team / Admin) plus basic
 * view access, so a soft-deleted/inaccessible trial still 404s or 403s
 * appropriately — **plus** a lock of its own, added 2026-09-16: once the
 * current version has been submitted into the approval chain
 * (TrialLineConfigurationReport::isSubmittedForApproval()), the maker can no
 * longer edit it at all (content or reassigning approvers) until either an
 * Admin overrides or it's Returned (see ReturnLineConfigurationReportRequest)
 * — otherwise the whole point of a maker-checker chain (data can't silently
 * change out from under an in-flight approval) would be defeated.
 */
class SaveTrialLineConfigurationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trial = Trial::whereNull('deleted_at')->where('id', $this->route('trial'))->firstOrFail();

        if (! Gate::allows('view', $trial) || ! Gate::allows('manage-line-configuration-report')) {
            return false;
        }

        $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->first();

        if ($report && $report->isSubmittedForApproval() && ! $this->user()->isAdmin()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'report_date' => ['nullable', 'date'],
            'client_name' => ['nullable', 'string', 'max:150'],
            'pic' => ['nullable', 'string', 'max:150'],
            'operator' => ['nullable', 'string', 'max:150'],
            'validation_name' => ['nullable', 'string', 'max:150'],
            // Plain free text, not numbers — see the migration's doc comment:
            // real preparers often write "General Pcs" or "10 Pcs (14%)"
            // here, not a bare integer.
            'total_qty' => ['nullable', 'string', 'max:50'],
            'setting_qty' => ['nullable', 'string', 'max:50'],
            'pass_qty' => ['nullable', 'string', 'max:50'],
            'ng_qty' => ['nullable', 'string', 'max:50'],
            'capacity_label' => ['nullable', 'string', 'max:50'],
            'opinion' => ['nullable', 'string', 'max:5000'],

            // Approved(PIE)/Checked(PROD) are no longer submitted here at
            // all — since the 2026-09-16 assign-an-approver-and-email
            // follow-up, only the assigned user's own dedicated
            // Approve/Checked button can set them (see
            // MarkTrialLineConfigurationReportSignOff), so this form only
            // ever picks *who* is assigned, not the done/by/at values
            // themselves. Approved(PIE) has no coded team in this system's
            // master data, so any active user is eligible; Checked(PROD)
            // must be someone on the PROD review team specifically —
            // enforced here (not just filtered in the UI's Combobox
            // options), so a direct POST can't assign an arbitrary user.
            'approved_pie_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('is_active', 1)->whereNull('deleted_at'),
            ],
            'checked_prod_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('is_active', 1)->whereNull('deleted_at')
                    ->where(fn ($query) => $query->whereRaw('UPPER(TRIM(review_unit)) = ?', ['PROD'])),
            ],

            // Return is its own dedicated, auto-stamped action now (see
            // ReturnTrialLineConfigurationReport) — not submitted here at all.
            'production_standard' => ['nullable', 'array'],
            'production_standard.*.line' => ['nullable', 'string', 'max:100'],
            'production_standard.*.workers' => ['nullable', 'string', 'max:100'],
            'production_standard.*.capacity' => ['nullable', 'string', 'max:100'],
            'production_standard.*.remark' => ['nullable', 'string', 'max:500'],

            'line_configuration' => ['nullable', 'array'],
            'line_configuration.*.no' => ['nullable', 'string', 'max:20'],
            'line_configuration.*.equipment' => ['nullable', 'string', 'max:150'],
            'line_configuration.*.process' => ['nullable', 'string', 'max:150'],
            'line_configuration.*.worker' => ['nullable', 'string', 'max:50'],
            'line_configuration.*.trial_status' => ['nullable', 'string', 'max:50'],
            'line_configuration.*.remark' => ['nullable', 'string', 'max:500'],
        ];
    }
}
