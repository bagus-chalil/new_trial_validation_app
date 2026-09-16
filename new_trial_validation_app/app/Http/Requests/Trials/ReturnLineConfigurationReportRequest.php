<?php

namespace App\Http\Requests\Trials;

use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates a Return action on a Line Configuration Report. Authorization
 * is per-report (TrialLineConfigurationReportPolicy::returnReport() —
 * whichever maker-checker stage is currently active), not the general
 * `manage-line-configuration-report` Gate.
 */
class ReturnLineConfigurationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trial = Trial::whereNull('deleted_at')->where('id', $this->route('trial'))->firstOrFail();
        $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->first();

        return $report !== null && Gate::allows('view', $trial) && Gate::allows('returnReport', $report);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    $words = preg_split('/\s+/', trim((string) $value)) ?: [];
                    $wordCount = count(array_filter($words));
                    if ($wordCount < 10) {
                        $fail('Alasan Return minimal 10 kata.');
                    }
                },
            ],
        ];
    }
}
