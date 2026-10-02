<?php

namespace App\Actions\Trials;

use App\Models\Trial;
use App\Models\ValidationParameter;
use Illuminate\Support\Facades\DB;

/**
 * Port of trial_completeness() in the legacy app's app/bootstrap.php:735-768
 * — the gate legacy's Report page checks before allowing "Submit for
 * Review". Checks the same header fields legacy does, plus that every
 * active validation_parameters row for the trial's product_type has a
 * decision (and NOT OK rows have a result + remark) — weighing/attachments
 * are deliberately NOT checked here, matching legacy exactly.
 */
class CheckTrialCompleteness
{
    /**
     * Required header fields, each mapped to its label key (shared with the
     * Report page's header section).
     *
     * @var array<string, string>
     */
    private const HEADER_REQUIRED = [
        'batch_number' => 'report.header.batch_number',
        'bulk_code' => 'report.header.bulk_code',
        'support_team' => 'report.header.support_team',
        'initiated_person_team' => 'report.header.initiated_person_team',
        'reason' => 'report.header.reason',
        'bom' => 'report.header.bom',
    ];

    /**
     * @return list<string>
     */
    public function __invoke(Trial $trial): array
    {
        $errors = [];

        foreach (self::HEADER_REQUIRED as $field => $label) {
            if (trim((string) $trial->{$field}) === '') {
                $errors[] = __('messages.completeness.required', ['field' => __($label)]);
            }
        }

        $params = ValidationParameter::query()
            ->where('product_type', $trial->product_type)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($params->isEmpty()) {
            $errors[] = __('messages.completeness.no_parameters', ['type' => $trial->product_type]);

            return $errors;
        }

        $results = DB::table('trials_results')
            ->where('trial_id', $trial->id)
            ->whereIn('parameter_id', $params->pluck('id'))
            ->get()
            ->keyBy('parameter_id');

        foreach ($params as $param) {
            $result = $results->get($param->id);

            if (! $result || ! in_array($result->decision, ['OK', 'NOT OK', 'N/A'], true)) {
                $errors[] = __('messages.completeness.no_decision', ['name' => $param->parameter_name]);

                continue;
            }

            if ($result->decision === 'NOT OK' && (trim((string) $result->result_value) === '' || trim((string) $result->remark) === '')) {
                $errors[] = __('messages.completeness.not_ok_incomplete', ['name' => $param->parameter_name]);
            }
        }

        return $errors;
    }
}
