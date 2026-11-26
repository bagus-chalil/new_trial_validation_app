<?php

namespace App\Services\Validation;

use App\Actions\Validation\AssignImprovementNumber;
use App\Models\ImNumber;
use App\Models\Improvement;
use App\Models\Mixing;
use App\Models\Project;
use App\Models\QcoNumber;
use App\Models\Tooling;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\TrialResult;
use App\Models\User;
use App\Models\ValidationAssessment;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Models\ValidationPlan;
use App\Models\ValidationTrialMeta;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Read model for the Product Journey: everything about one Case (attempts and
 * their results, linked projects, line configuration) built from data that
 * already exists. Attempts are counted per Case, never per product_id (the
 * product master has duplicate FG codes and N/A codes).
 */
class CaseJourney
{
    /**
     * @return array<string, mixed>
     */
    public function build(ValidationCase $case, User $user): array
    {
        $case->loadMissing('product');

        $trialLinks = ValidationCaseLink::query()
            ->where('case_id', $case->id)
            ->where('kind', 'trial')
            ->get();

        $trials = Trial::query()
            ->whereNull('deleted_at')
            ->whereIn('id', $trialLinks->pluck('ref_id'))
            ->with('creator:id,name,email')
            ->get()
            ->filter(fn (Trial $t) => TrialOutcome::isAttempt($t))
            ->sortBy(fn (Trial $t) => sprintf('%s-%010d', $t->created_at?->format('YmdHis'), $t->id))
            ->values();

        $attempts = $this->attempts($trials, $case, $user);

        $counts = [];
        foreach ($attempts as $attempt) {
            $counts[$attempt['result']] = ($counts[$attempt['result']] ?? 0) + 1;
        }

        $leadTimes = array_filter(array_column($attempts, 'lead_time_days'), fn ($d) => $d !== null);

        return [
            'case' => [
                'id' => $case->id,
                'case_no' => $case->case_no,
                'case_type' => $case->case_type,
                'created_at' => $case->created_at?->toDateString(),
                'product_id' => $case->product_id,
                // FG always comes from the master row, never the trial snapshot.
                'product_name' => $case->product->product_name ?? $case->product_name,
                'finish_good_code' => $case->product?->finish_good_code,
            ],
            'summary' => [
                'total' => count($attempts),
                'counts' => $counts,
                'last_result' => $attempts === [] ? null : $attempts[array_key_last($attempts)]['result'],
                'avg_lead_time_days' => $leadTimes === [] ? null : round(array_sum($leadTimes) / count($leadTimes), 1),
                'lead_time_samples' => count($leadTimes),
                'first_trial_date' => $attempts === [] ? null : $attempts[0]['validation_date'],
            ],
            'attempts' => $attempts,
            'projects' => $this->projects($case),
        ];
    }

    /**
     * Tahap 2 extras for the Case page only (kept out of build() so the list
     * page does not run these queries once per row): the Assessment that
     * started the Case, linked Check Tooling requests, and weekly plans.
     *
     * @return array<string, mixed>
     */
    public function extras(ValidationCase $case): array
    {
        // The Tahap 2 migration may not have run on this server yet.
        if (! Schema::hasTable('vd_assessments') || ! Schema::hasTable('vd_validation_plans')) {
            return ['assessment' => null, 'toolings' => [], 'availableToolings' => [], 'improvements' => [], 'availableImprovements' => [], 'plans' => []];
        }

        $assessment = ValidationAssessment::query()->whereNull('deleted_at')->where('case_id', $case->id)->first();

        $toolingIds = ValidationCaseLink::query()
            ->where('case_id', $case->id)
            ->where('kind', 'tooling')
            ->pluck('ref_id');

        $linked = Tooling::query()
            ->whereNull('deleted_at')
            ->whereIn('id', $toolingIds)
            ->get(['id', 'npd_no', 'product_name', 'status']);

        $taken = ValidationCaseLink::query()->where('kind', 'tooling')->pluck('ref_id');

        $improvementIds = ValidationCaseLink::query()
            ->where('case_id', $case->id)
            ->where('kind', 'improvement')
            ->pluck('ref_id');
        $improvementsTaken = ValidationCaseLink::query()->where('kind', 'improvement')->pluck('ref_id');
        $linkedImprovements = Improvement::query()
            ->whereNull('deleted_at')
            ->whereIn('id', $improvementIds)
            ->get(['id', 'title', 'status', 'project_id']);
        $numbers = AssignImprovementNumber::numbersFor($linkedImprovements->pluck('id'));

        return [
            'assessment' => $assessment === null ? null : [
                'id' => $assessment->id,
                'receive_date' => $assessment->receive_date?->toDateString(),
                'client' => $assessment->client,
                'sales_month' => $assessment->sales_month,
                'qty_po' => $assessment->qty_po,
                'packaging_type' => $assessment->packaging_type,
                'validation_scope' => $assessment->validation_scope ?? [],
                'recommended_machine' => $assessment->recommended_machine,
                'qco_no' => $assessment->qco_no,
                'qco_link' => $assessment->qco_link,
                'pm_received_date' => $assessment->pm_received_date?->toDateString(),
                'pm_na' => $assessment->pm_na,
                'bulk_received_date' => $assessment->bulk_received_date?->toDateString(),
                'bulk_na' => $assessment->bulk_na,
                'risk_level' => $assessment->risk_level,
                'created_by_name' => $assessment->created_by_name,
            ],
            'toolings' => $linked->map(fn (Tooling $t) => [
                'id' => $t->id,
                'npd_no' => $t->npd_no,
                'product_name' => $t->product_name,
                'status' => $t->status,
            ])->values()->all(),
            // Not yet tied to any Case: the pick list of the link dialog.
            'availableToolings' => Tooling::query()
                ->whereNull('deleted_at')
                ->whereNotIn('id', $taken)
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'npd_no', 'product_name'])
                ->map(fn (Tooling $t) => ['id' => $t->id, 'label' => trim($t->npd_no.' · '.$t->product_name, ' ·')])
                ->values()
                ->all(),
            'improvements' => $linkedImprovements->map(fn (Improvement $i) => [
                'id' => $i->id,
                'number' => $numbers[$i->id] ?? null,
                'title' => $i->title,
                'status' => $i->status,
                'project_id' => $i->project_id,
            ])->values()->all(),
            // Not yet tied to any Case: the pick list of the link dialog.
            'availableImprovements' => Improvement::query()
                ->whereNull('deleted_at')
                ->whereNotIn('id', $improvementsTaken)
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'title'])
                ->map(fn (Improvement $i) => ['id' => $i->id, 'label' => $i->title])
                ->values()
                ->all(),
            'plans' => ValidationPlan::query()
                ->whereNull('deleted_at')
                ->where('case_id', $case->id)
                ->orderByDesc('week_start')
                ->get()
                ->map(fn (ValidationPlan $p) => [
                    'id' => $p->id,
                    'week_start' => $p->week_start->toDateString(),
                    'status' => $p->status,
                    'ppic_note' => $p->ppic_note,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Mixing records (Tahap 4) linked to this Case through vd_case_links kind `mixing`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mixings(ValidationCase $case): array
    {
        if (! Schema::hasTable('vd_mixings')) {
            return [];
        }

        return Mixing::query()
            ->whereNull('deleted_at')
            ->whereIn('id', ValidationCaseLink::query()->where('case_id', $case->id)->where('kind', 'mixing')->pluck('ref_id'))
            ->orderBy('id')
            ->get()
            ->map(fn (Mixing $m) => [
                'id' => $m->id,
                'mixing_code' => $m->mixing_code,
                'batch_no' => $m->batch_no,
                'mixing_date' => $m->mixing_date?->toDateString(),
                'progress_status' => $m->progress_status,
                'batch_record_revision' => $m->batch_record_revision,
            ])
            ->values()
            ->all();
    }

    /**
     * Tahap 3: QCO / IM numbers recorded on the Case, plus the issued numbers
     * not tied to any Case yet (pick list of the link dialog) and who may link.
     *
     * @return array<string, mixed>
     */
    public function numbers(ValidationCase $case, User $user): array
    {
        $empty = ['numbers' => ['qco' => [], 'im' => []], 'availableNumbers' => ['qco' => [], 'im' => []], 'canLinkNumbers' => ['qco' => false, 'im' => false]];

        if (! Schema::hasTable('vd_qco_numbers') || ! Schema::hasTable('vd_im_numbers')) {
            return $empty;
        }

        $links = ValidationCaseLink::query()->where('case_id', $case->id)->whereIn('kind', ['qco', 'im'])->get();
        $takenQco = ValidationCaseLink::query()->where('kind', 'qco')->pluck('ref_key');
        $takenIm = ValidationCaseLink::query()->where('kind', 'im')->pluck('ref_key');

        $qco = QcoNumber::query()->whereIn('number', $links->where('kind', 'qco')->pluck('ref_key'))->get();
        $im = ImNumber::query()->whereIn('number', $links->where('kind', 'im')->pluck('ref_key'))->get();

        return [
            'numbers' => [
                'qco' => $qco->map(fn (QcoNumber $n) => [
                    'id' => $n->id, 'number' => $n->number, 'status' => $n->status, 'client' => $n->client,
                ])->values()->all(),
                'im' => $im->map(fn (ImNumber $n) => [
                    'id' => $n->id, 'number' => $n->number, 'status' => $n->status, 'version_no' => $n->version_no,
                ])->values()->all(),
            ],
            'availableNumbers' => [
                'qco' => QcoNumber::query()->where('status', 'Issued')->whereNotIn('number', $takenQco)
                    ->orderByDesc('id')->limit(200)->get()
                    ->map(fn (QcoNumber $n) => ['id' => $n->id, 'label' => trim($n->number.' · '.$n->product_name, ' ·')])->values()->all(),
                'im' => ImNumber::query()->where('status', '!=', 'Void')->whereNotIn('number', $takenIm)
                    ->orderByDesc('id')->limit(200)->get()
                    ->map(fn (ImNumber $n) => ['id' => $n->id, 'label' => $n->number])->values()->all(),
            ],
            'canLinkNumbers' => [
                'qco' => ValidationRoles::isPie($user) || ValidationRoles::isPrni($user),
                'im' => ValidationRoles::isPie($user) || ValidationRoles::isQa($user),
            ],
        ];
    }

    /**
     * @param  Collection<int, Trial>  $trials
     * @return list<array<string, mixed>>
     */
    private function attempts(Collection $trials, ValidationCase $case, User $user): array
    {
        $ids = $trials->pluck('id')->all();

        $notOk = TrialResult::query()
            ->whereIn('trial_id', $ids)
            ->where('decision', 'NOT OK')
            ->distinct()
            ->pluck('trial_id')
            ->flip();

        $meta = ValidationTrialMeta::query()->whereIn('trial_id', $ids)->get()->keyBy('trial_id');

        $lineConfigs = TrialLineConfigurationReport::query()
            ->whereIn('trial_id', $ids)
            ->where('is_locked', false)
            ->get()
            ->keyBy('trial_id');

        $masterFg = $case->product?->finish_good_code;

        $out = [];
        foreach ($trials->values() as $index => $trial) {
            /** @var ValidationTrialMeta|null $m */
            $m = $meta->get($trial->id);
            $hasNotOk = $notOk->has($trial->id);
            $client = (bool) $m?->client_approved;

            $line = $lineConfigs->get($trial->id);

            $out[] = [
                'attempt_no' => $index + 1,
                'id' => $trial->id,
                'trial_code' => $trial->trial_code,
                'status' => $trial->progress_status,
                'validation_date' => $trial->validation_date?->toDateString(),
                'created_at' => $trial->created_at?->toDateString(),
                'decided_at' => ($trial->approved_at ?? $trial->rejected_at)?->toDateString(),
                'revision_no' => (int) $trial->revision_no,
                'pending_with' => $trial->pending_with,
                'creator' => $trial->creator->name ?? $trial->created_by,
                'result' => TrialOutcome::result($trial, $hasNotOk, $client),
                'has_not_ok' => $hasNotOk,
                'client_approved' => $client,
                'client_approval_note' => $m?->client_approval_note,
                'pm_ready_date' => $m?->pm_ready_date?->toDateString(),
                'bulk_ready_date' => $m?->bulk_ready_date?->toDateString(),
                'dates_defaulted' => (bool) $m?->dates_defaulted,
                'lead_time_days' => $m === null ? null : self::leadTimeDays($trial->validation_date, $m),
                'fg_snapshot_stale' => $masterFg !== null && trim((string) $trial->finish_good_code) !== trim($masterFg),
                'line_config' => $line === null ? null : self::lineConfigStatus($line),
                'can_view' => Gate::forUser($user)->allows('view', $trial),
            ];
        }

        return $out;
    }

    /**
     * Realized trial lead time: trial date minus the later of the PM / Bulk
     * ready dates. Null for defaulted (backfilled H-1) dates, which are
     * placeholders and would make every old trial read as "1 day".
     */
    public static function leadTimeDays(?CarbonInterface $validationDate, ValidationTrialMeta $meta): ?int
    {
        if ($validationDate === null || $meta->dates_defaulted) {
            return null;
        }

        $ready = collect([$meta->pm_ready_date, $meta->bulk_ready_date])->filter()->max();
        if ($ready === null) {
            return null;
        }

        return (int) $ready->copy()->startOfDay()->diffInDays($validationDate->copy()->startOfDay(), false);
    }

    private static function lineConfigStatus(TrialLineConfigurationReport $report): string
    {
        if ($report->approved_pie && $report->checked_prod) {
            return 'Approved';
        }

        return $report->submitted_at === null ? 'Draft' : 'Submitted';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projects(ValidationCase $case): array
    {
        $ids = ValidationCaseLink::query()
            ->where('case_id', $case->id)
            ->where('kind', 'project')
            ->pluck('ref_id');

        return Project::query()
            ->whereNull('deleted_at')
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'status' => $p->status,
                'progress' => $p->progress,
            ])
            ->values()
            ->all();
    }

    /**
     * Realized Trial Lead Time KPI (NPD only): average and sample count over
     * NPD attempts that carry real (non-defaulted) PM/Bulk ready dates.
     *
     * @param  array{from: string, to: string}|null  $period  on trial validation_date
     * @return array{avg_days: float|null, samples: int, defaulted: int, total: int}
     */
    public static function realizedLeadTimeKpi(?array $period = null): array
    {
        $rows = DB::table('vd_case_links as l')
            ->join('vd_cases as c', 'c.id', '=', 'l.case_id')
            ->join('trials_header as t', 't.id', '=', 'l.ref_id')
            ->leftJoin('vd_trial_meta as m', 'm.trial_id', '=', 't.id')
            ->where('l.kind', 'trial')
            ->where('c.case_type', 'NPD')
            ->whereNull('c.deleted_at')
            ->whereNull('t.deleted_at')
            ->where('t.progress_status', '!=', 'Draft')
            ->when($period !== null, fn ($q) => $q->whereBetween('t.validation_date', [$period['from'], $period['to']]))
            ->get(['t.validation_date', 'm.pm_ready_date', 'm.bulk_ready_date', 'm.dates_defaulted']);

        $days = [];
        $defaulted = 0;
        foreach ($rows as $row) {
            if ($row->validation_date === null) {
                continue;
            }

            if ((bool) $row->dates_defaulted) {
                $defaulted++;

                continue;
            }

            $ready = collect([$row->pm_ready_date, $row->bulk_ready_date])
                ->filter()
                ->map(fn ($d) => Carbon::parse($d)->startOfDay())
                ->max();

            if ($ready === null) {
                continue;
            }

            $days[] = (int) $ready->diffInDays(Carbon::parse($row->validation_date)->startOfDay(), false);
        }

        return [
            'avg_days' => $days === [] ? null : round(array_sum($days) / count($days), 1),
            'samples' => count($days),
            'defaulted' => $defaulted,
            'total' => count($rows),
        ];
    }
}
