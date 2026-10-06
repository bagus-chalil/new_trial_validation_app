<?php

namespace App\Http\Requests\Trials;

use App\Models\LineConfigurationLane;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a Line Configuration Report save. Deliberately authorized
 * differently from every other wizard-step FormRequest in this app (which
 * all gate on `update`, i.e. the trial's own edit-rights window): this form
 * has no lock tied to the *trial's* status, so it's gated purely on
 * `manage-line-configuration-report` (the 'checked_prod' lane's required
 * team, admin-configurable — see LineConfigurationLane — / Admin) plus basic
 * view access, so a soft-deleted/inaccessible trial still 404s or 403s
 * appropriately — **plus** two further narrowings once a report already has
 * a recorded drafter: (1) only that drafter (updated_by_user_id — see
 * TrialLineConfigurationReport::blocksEditFor()) may keep editing it, not
 * just any PROD-team member — being on the PROD team only grants the right
 * to start/claim a report nobody has drafted yet; (2) a lock of its own,
 * added 2026-09-16: once the current version has been submitted into the
 * approval chain (TrialLineConfigurationReport::isSubmittedForApproval()),
 * even the drafter can no longer edit it at all (content or reassigning
 * approvers) until either an Admin overrides or it's Returned (see
 * ReturnLineConfigurationReportRequest) — otherwise the whole point of a
 * maker-checker chain (data can't silently change out from under an
 * in-flight approval) would be defeated. Admin bypasses both narrowings.
 */
class SaveTrialLineConfigurationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trial = Trial::whereNull('deleted_at')->where('id', $this->route('trial'))->firstOrFail();

        if (! Gate::allows('view', $trial) || ! Gate::allows('manage-line-configuration-report')) {
            return false;
        }

        if ($this->user()->isAdmin()) {
            return true;
        }

        $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->first();

        if (! $report) {
            return true;
        }

        return ! $report->blocksEditFor($this->user()) && ! $report->isSubmittedForApproval();
    }

    /**
     * Blank "Tambah Baris" rows are dropped *before* validation (moved here
     * from the controller), so the every-cell-required rules below only
     * apply to rows the preparer actually started filling in — while a
     * report with zero real rows still fails `min:1`.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isDraft()) {
            // A draft never enters the approval chain, so it never carries
            // approvers — assigning one is exactly what submits/locks the
            // report (TrialLineConfigurationReport::isSubmittedForApproval())
            // and emails the assignee.
            $this->merge(['approved_pie_user_id' => null, 'checked_prod_user_id' => null]);
        }

        $this->merge([
            'production_standard' => $this->dropBlankRows($this->input('production_standard', [])),
            // 'trial_status' is ignored the same way 'no' already is: the
            // frontend's Pass/No Trial toggle always submits a real value
            // (defaulting to 'No Trial'), so it alone must never keep an
            // otherwise fully-empty "Tambah Baris" row from being dropped.
            'line_configuration' => $this->dropBlankRows($this->input('line_configuration', []), ['no', 'trial_status']),
        ]);
    }

    /**
     * Two save modes (2026-10-02, per user request): `intent=draft` keeps
     * the old everything-optional rules so a preparer can save partial work
     * without entering the approval chain; `intent=submit` (the default, so
     * a missing intent is never the lenient path) requires every field and
     * both approvers — saving that way *is* submitting into the approval
     * chain (see TrialLineConfigurationReport::isSubmittedForApproval()).
     * capacity_label always stays optional, since it's hidden from the form.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isDraft() ? 'nullable' : 'required';

        return [
            'intent' => ['nullable', Rule::in(['draft', 'submit'])],
            'report_date' => [$required, 'date'],
            'client_name' => [$required, 'string', 'max:150'],
            'pic' => [$required, 'string', 'max:150'],
            'operator' => [$required, 'string', 'max:150'],
            'validation_name' => [$required, 'string', 'max:150'],
            // Plain free text, not numbers — see the migration's doc comment:
            // real preparers often write "General Pcs" or "10 Pcs (14%)"
            // here, not a bare integer.
            'total_qty' => [$required, 'string', 'max:50'],
            'setting_qty' => [$required, 'string', 'max:50'],
            'pass_qty' => [$required, 'string', 'max:50'],
            'ng_qty' => [$required, 'string', 'max:50'],
            'capacity_label' => ['nullable', 'string', 'max:50'],
            'opinion' => [$required, 'string', 'max:5000'],

            // Approved(PIE)/Checked(PROD) are no longer submitted here at
            // all — since the 2026-09-16 assign-an-approver-and-email
            // follow-up, only the assigned user's own dedicated
            // Approve/Checked button can set them (see
            // MarkTrialLineConfigurationReportSignOff), so this form only
            // ever picks *who* is assigned, not the done/by/at values
            // themselves. Which team (if any) each stage requires is now
            // admin-configurable (Phase 3 of the RBAC/Team-master redesign —
            // see LineConfigurationLane) instead of a hardcoded 'PROD'
            // literal — enforced here (not just filtered in the UI's
            // Combobox options), so a direct POST can't assign an
            // ineligible user. Both are required on submit; a draft never
            // carries them (see prepareForValidation()).
            'approved_pie_user_id' => [
                $required, 'integer',
                Rule::exists('users', 'id')->where('is_active', 1)->whereNull('deleted_at')
                    ->where(fn ($query) => LineConfigurationLane::constrainToStage($query, 'approved_pie')),
            ],
            'checked_prod_user_id' => [
                $required, 'integer',
                Rule::exists('users', 'id')->where('is_active', 1)->whereNull('deleted_at')
                    ->where(fn ($query) => LineConfigurationLane::constrainToStage($query, 'checked_prod')),
            ],

            // Return is its own dedicated, auto-stamped action now (see
            // ReturnTrialLineConfigurationReport) — not submitted here at all.
            'production_standard' => [$required, 'array', ...($this->isDraft() ? [] : ['min:1'])],
            'production_standard.*.line' => [$required, 'string', 'max:100'],
            'production_standard.*.workers' => [$required, 'string', 'max:100'],
            'production_standard.*.capacity' => [$required, 'string', 'max:100'],
            'production_standard.*.remark' => [$required, 'string', 'max:500'],

            // The Line Configuration table is optional even on submit
            // (2026-10-06, per user request): real lines often have steps
            // with no equipment/worker/remark, so any blank cell — or the
            // whole table — is allowed and simply rendered as "-" in the
            // report/PDF instead of blocking the save.
            'line_configuration' => ['nullable', 'array'],
            'line_configuration.*.no' => ['nullable', 'string', 'max:20'],
            'line_configuration.*.equipment' => ['nullable', 'string', 'max:150'],
            'line_configuration.*.process' => ['nullable', 'string', 'max:150'],
            'line_configuration.*.worker' => ['nullable', 'string', 'max:50'],
            'line_configuration.*.trial_status' => ['nullable', 'string', Rule::in(['Pass', 'No Trial'])],
            'line_configuration.*.remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $standardRows = __('line_config.validation.rows_required', ['section' => __('line_config.sections.production_standard')]);
        $configRows = __('line_config.validation.rows_required', ['section' => __('line_config.sections.line_configuration')]);

        return [
            'production_standard.required' => $standardRows,
            'production_standard.min' => $standardRows,
            'line_configuration.required' => $configRows,
            'line_configuration.min' => $configRows,
            'production_standard.*.*.required' => __('line_config.validation.row_field_required'),
            'line_configuration.*.*.required' => __('line_config.validation.row_field_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $lanes = LineConfigurationLane::labels();

        return [
            'report_date' => __('line_config.fields.date'),
            'client_name' => __('line_config.fields.client'),
            'pic' => __('line_config.fields.pic'),
            'operator' => __('line_config.fields.operator'),
            'validation_name' => __('line_config.fields.validation'),
            'total_qty' => __('line_config.fields.total'),
            'setting_qty' => __('line_config.fields.setting'),
            'pass_qty' => __('line_config.fields.pass'),
            'ng_qty' => __('line_config.fields.ng'),
            'opinion' => __('line_config.fields.opinion'),
            'approved_pie_user_id' => $lanes['approved_pie'],
            'checked_prod_user_id' => $lanes['checked_prod'],
            'production_standard.*.line' => __('line_config.columns.line'),
            'production_standard.*.workers' => __('line_config.columns.workers'),
            'production_standard.*.capacity' => __('line_config.columns.capacity'),
            'production_standard.*.remark' => __('line_config.columns.remark'),
            'line_configuration.*.equipment' => __('line_config.columns.equipment'),
            'line_configuration.*.process' => __('line_config.columns.process'),
            'line_configuration.*.worker' => __('line_config.columns.worker'),
            'line_configuration.*.trial_status' => __('line_config.columns.trial'),
            'line_configuration.*.remark' => __('line_config.columns.remark'),
        ];
    }

    /**
     * An already-submitted report (only reachable here by an Admin
     * override) can't be demoted back to a draft — that would silently
     * clear its assigned approvers mid-approval.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->isDraft()) {
                    return;
                }

                $report = TrialLineConfigurationReport::where('trial_id', $this->route('trial'))->where('is_locked', false)->first();

                if ($report?->isSubmittedForApproval()) {
                    $validator->errors()->add('intent', __('line_config.validation.already_submitted'));
                }
            },
        ];
    }

    public function isDraft(): bool
    {
        return $this->input('intent') === 'draft';
    }

    /**
     * Drops any row whose fields are all blank — the frontend always renders
     * a handful of empty rows by default, and there's no reason to persist
     * rows the reviewer never actually filled in. `$ignoreKeys` excludes
     * fields that are always populated regardless of user input (e.g. the
     * Line Configuration table's auto-numbered `no` column) from that check.
     *
     * @param  array<int, string>  $ignoreKeys
     * @return array<int, mixed>
     */
    private function dropBlankRows(mixed $rows, array $ignoreKeys = []): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, function (mixed $row) use ($ignoreKeys): bool {
            if (! is_array($row)) {
                return false;
            }

            foreach ($row as $key => $value) {
                if (in_array($key, $ignoreKeys, true)) {
                    continue;
                }

                if (trim((string) $value) !== '') {
                    return true;
                }
            }

            return false;
        }));
    }
}
