<?php

namespace App\Http\Controllers;

use App\Actions\Trials\SaveTrialLineConfigurationReport;
use App\Http\Requests\Trials\SaveTrialLineConfigurationReportRequest;
use App\Models\Trial;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The Line Configuration Report has no dedicated page of its own — it's
 * embedded directly into the per-trial Report Summary page
 * (TrialReportController::show() / resources/js/pages/trials/report.tsx),
 * the same "detail + act on one screen" page the Review/Approval Queues
 * already link into (see ux_detail_then_act_principle). This controller only
 * needs the write side.
 */
class TrialLineConfigurationReportController extends Controller
{
    public function update(SaveTrialLineConfigurationReportRequest $request, int $trial, SaveTrialLineConfigurationReport $action): RedirectResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);

        $data = $request->validated();
        $data['production_standard'] = $this->dropBlankRows($data['production_standard'] ?? []);
        $data['line_configuration'] = $this->dropBlankRows($data['line_configuration'] ?? []);

        $action($trial, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Line Configuration Report berhasil disimpan.']);

        return to_route('trials.report.show', $trial->id);
    }

    /**
     * Drops any row whose fields are all blank — the frontend always renders
     * a handful of empty rows by default, and there's no reason to persist
     * rows the reviewer never actually filled in.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function dropBlankRows(array $rows): array
    {
        return array_values(array_filter($rows, function (array $row) {
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    return true;
                }
            }

            return false;
        }));
    }
}
