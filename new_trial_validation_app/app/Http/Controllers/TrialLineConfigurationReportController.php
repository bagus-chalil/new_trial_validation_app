<?php

namespace App\Http\Controllers;

use App\Actions\Trials\MarkTrialLineConfigurationReportSignOff;
use App\Actions\Trials\ReturnTrialLineConfigurationReport;
use App\Actions\Trials\SaveTrialLineConfigurationReport;
use App\Http\Requests\Trials\ReturnLineConfigurationReportRequest;
use App\Http\Requests\Trials\SaveTrialLineConfigurationReportRequest;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Services\Pdf\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The Line Configuration Report has no dedicated page of its own — it's
 * embedded directly into the per-trial Report Summary page
 * (TrialReportController::show() / resources/js/pages/trials/report.tsx),
 * the same "detail + act on one screen" page the Review/Approval Queues
 * already link into (see ux_detail_then_act_principle).
 *
 * update() writes the *current* (unlocked) version — blocked once the
 * report has been submitted into the approval chain, see
 * SaveTrialLineConfigurationReportRequest. approvePie()/checkProd() are the
 * assigned sign-off user's own one-click confirmations, and returnReport()
 * is the "send it back" alternative available to whichever stage is
 * currently active — all three deliberately separate actions/authorization
 * from update(), since the assignee is very often not a PROD reviewer/Admin
 * at all (see App\Policies\TrialLineConfigurationReportPolicy).
 * downloadVersion() serves a locked historical version as a read-only PDF —
 * see TrialLineConfigurationReport's doc comment on versioning.
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

    public function approvePie(Request $request, int $trial, MarkTrialLineConfigurationReportSignOff $action): RedirectResponse
    {
        return $this->signOff($request, $trial, 'approved_pie', $action);
    }

    public function checkProd(Request $request, int $trial, MarkTrialLineConfigurationReportSignOff $action): RedirectResponse
    {
        return $this->signOff($request, $trial, 'checked_prod', $action);
    }

    /**
     * @param  'approved_pie'|'checked_prod'  $field
     */
    private function signOff(Request $request, int $trialId, string $field, MarkTrialLineConfigurationReportSignOff $action): RedirectResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trialId);
        $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->firstOrFail();

        Gate::authorize($field === 'approved_pie' ? 'approvePie' : 'checkProd', $report);

        $action($trial, $report, $field, $request->user());

        $label = $field === 'approved_pie' ? 'Approved (PIE)' : 'Checked (PROD)';
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$label} berhasil dikonfirmasi."]);

        return to_route('trials.report.show', $trial->id);
    }

    public function returnReport(ReturnLineConfigurationReportRequest $request, int $trial, ReturnTrialLineConfigurationReport $action): RedirectResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);
        $report = TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->firstOrFail();

        $action($trial, $report, $request->string('reason')->trim()->value(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Line Configuration Report dikembalikan untuk revisi.']);

        return to_route('trials.report.show', $trial->id);
    }

    /**
     * A locked historical version can only be downloaded, never viewed
     * in-app or edited — see TrialLineConfigurationReport's doc comment.
     */
    public function downloadVersion(Request $request, int $trial, int $version, PdfService $pdf): HttpResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);

        Gate::authorize('view', $trial);

        $report = TrialLineConfigurationReport::where('trial_id', $trial->id)
            ->where('version', $version)
            ->where('is_locked', true)
            ->firstOrFail();

        return $pdf->fromView('pdf.line-configuration-report-version', [
            'title' => 'Line Configuration Report — '.$trial->trial_code.' (v'.$version.')',
            'trial' => $trial,
            'report' => $report,
        ], "LineConfigurationReport-{$trial->trial_code}-v{$version}.pdf");
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
