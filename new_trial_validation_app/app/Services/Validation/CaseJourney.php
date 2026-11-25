<?php

namespace App\Services\Validation;

use App\Models\Project;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\TrialResult;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Models\ValidationTrialMeta;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
