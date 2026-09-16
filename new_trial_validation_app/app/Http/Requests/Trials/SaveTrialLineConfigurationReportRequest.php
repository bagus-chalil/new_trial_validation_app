<?php

namespace App\Http\Requests\Trials;

use App\Models\Trial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates a Line Configuration Report save. Deliberately authorized
 * differently from every other wizard-step FormRequest in this app (which
 * all gate on `update`, i.e. the trial's own edit-rights window): this form
 * has no lock and no relationship to the trial's editability, so it's gated
 * purely on `manage-line-configuration-report` (PROD review team / Admin)
 * plus basic view access, so a soft-deleted/inaccessible trial still 404s
 * or 403s appropriately.
 */
class SaveTrialLineConfigurationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trial = Trial::whereNull('deleted_at')->where('id', $this->route('trial'))->firstOrFail();

        return Gate::allows('view', $trial) && Gate::allows('manage-line-configuration-report');
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
