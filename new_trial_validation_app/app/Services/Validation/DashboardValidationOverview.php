<?php

namespace App\Services\Validation;

use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregates for the v2.0 Dashboard (worksheet "DB Validation", tab TRIAL
 * VALIDATION APPS, column AC7): NPD risk, NPD vs Improvement per status,
 * NPD product type, Line Configuration, and the Mixing view. Read-only and
 * always scoped through Trial::visibleTo() so the numbers match what the
 * viewer may open.
 */
class DashboardValidationOverview
{
    /** Stored trial status => bucket key shown on the dashboard. */
    private const STATUS_BUCKETS = [
        'In Review' => 'in_review',
        'Ready for Approval' => 'awaiting_approval',
        'Approved' => 'done',
        'Need Revision' => 'revision_required',
        'Rejected' => 'rejected',
    ];

    /**
     * @param  array{from: string, to: string}|null  $period  trials created within
     * @return array<string, mixed>
     */
    public static function build(User $user, ?array $period): array
    {
        $hasCases = Schema::hasTable('vd_cases') && Schema::hasTable('vd_case_links');

        return [
            'risk' => Schema::hasTable('vd_assessments') ? self::risk($period) : null,
            'npd' => $hasCases ? self::caseTypeStatuses($user, 'NPD', $period) : null,
            'improvement' => $hasCases ? self::caseTypeStatuses($user, 'IMP', $period) : null,
            'npdProductTypes' => $hasCases ? self::npdProductTypes($user, $period) : [],
            'lineConfig' => Schema::hasTable('trial_line_configuration_reports') ? self::lineConfig($period) : null,
            'mixing' => self::mixing($user, $period),
        ];
    }

    /**
     * @param  array{from: string, to: string}|null  $period
     * @return array{high: int, medium: int, low: int}
     */
    private static function risk(?array $period): array
    {
        $rows = DB::table('vd_assessments')
            ->whereNull('deleted_at')
            ->when($period !== null, fn ($q) => $q->whereBetween('created_at', self::range($period)))
            ->selectRaw('risk_level, COUNT(*) as c')
            ->groupBy('risk_level')
            ->pluck('c', 'risk_level');

        return [
            'high' => (int) ($rows['High'] ?? 0),
            'medium' => (int) ($rows['Medium'] ?? 0),
            'low' => (int) ($rows['Low'] ?? 0),
        ];
    }

    /**
     * Trials linked to a Case of the given type, counted per status bucket.
     * Drafts are not "diTrial" yet, so they are left out of the total.
     *
     * @param  array{from: string, to: string}|null  $period
     * @return array<string, int>
     */
    private static function caseTypeStatuses(User $user, string $caseType, ?array $period): array
    {
        $counts = self::trialsOfCaseType($user, $caseType, $period)
            ->whereIn('progress_status', array_keys(self::STATUS_BUCKETS))
            ->selectRaw('progress_status, COUNT(*) as c')
            ->groupBy('progress_status')
            ->pluck('c', 'progress_status');

        $out = ['total' => 0];
        foreach (self::STATUS_BUCKETS as $status => $key) {
            $out[$key] = (int) ($counts[$status] ?? 0);
            $out['total'] += $out[$key];
        }

        return $out;
    }

    /**
     * @param  array{from: string, to: string}|null  $period
     * @return array<int, array{label: string, count: int}>
     */
    private static function npdProductTypes(User $user, ?array $period): array
    {
        return self::trialsOfCaseType($user, 'NPD', $period)
            ->where('progress_status', '!=', 'Draft')
            ->selectRaw("COALESCE(NULLIF(product_type, ''), '-') as label, COUNT(*) as c")
            ->groupBy('label')
            ->orderByDesc('c')
            ->limit(8)
            ->toBase()
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'count' => (int) $r->c])
            ->all();
    }

    /**
     * @param  array{from: string, to: string}|null  $period
     * @return Builder<Trial>
     */
    private static function trialsOfCaseType(User $user, string $caseType, ?array $period): Builder
    {
        return Trial::query()
            ->visibleTo($user)
            ->createdWithin($period)
            ->whereIn('trials_header.id', DB::table('vd_case_links as l')
                ->join('vd_cases as c', 'c.id', '=', 'l.case_id')
                ->where('l.kind', 'trial')
                ->where('c.case_type', $caseType)
                ->whereNull('c.deleted_at')
                ->select('l.ref_id'));
    }

    /**
     * Line Configuration reports per stage of the PROD acknowledge -> PIE
     * Leader approve chain. "Bottleneck" is the side that currently holds
     * the most reports.
     *
     * @param  array{from: string, to: string}|null  $period
     * @return array<string, mixed>
     */
    private static function lineConfig(?array $period): array
    {
        $reports = TrialLineConfigurationReport::query()
            ->whereNotNull('submitted_at')
            ->when($period !== null, fn ($q) => $q->whereBetween('created_at', self::range($period)))
            ->get(['checked_prod', 'approved_pie', 'return_prod', 'submitted_at']);

        $in = ['in_review' => 0, 'awaiting_approval' => 0, 'done' => 0, 'revision_required' => 0];
        foreach ($reports as $r) {
            if ($r->return_prod) {
                $in['revision_required']++;
            } elseif ($r->checked_prod && $r->approved_pie) {
                $in['done']++;
            } elseif ($r->checked_prod) {
                $in['awaiting_approval']++;
            } else {
                $in['in_review']++;
            }
        }

        $draft = TrialLineConfigurationReport::query()
            ->whereNull('submitted_at')
            ->when($period !== null, fn ($q) => $q->whereBetween('created_at', self::range($period)))
            ->count();

        $bottleneck = null;
        if ($in['in_review'] > 0 || $in['awaiting_approval'] > 0) {
            $bottleneck = $in['in_review'] >= $in['awaiting_approval']
                ? ['department' => 'PROD', 'count' => $in['in_review']]
                : ['department' => 'PIE', 'count' => $in['awaiting_approval']];
        }

        return $in + [
            'qty' => $reports->count(),
            'draft' => $draft,
            'bottleneck' => $bottleneck,
        ];
    }

    /**
     * Mixing view: 1st-batch mixing trials (product type "Mixing") per status
     * and the machines used. Risk / mixing type / RNI lead time have no data
     * source yet.
     *
     * @param  array{from: string, to: string}|null  $period
     * @return array<string, mixed>
     */
    private static function mixing(User $user, ?array $period): array
    {
        $base = fn () => Trial::query()->visibleTo($user)->createdWithin($period)->where('product_type', 'Mixing');

        $counts = $base()
            ->whereIn('progress_status', array_keys(self::STATUS_BUCKETS))
            ->selectRaw('progress_status, COUNT(*) as c')
            ->groupBy('progress_status')
            ->pluck('c', 'progress_status');

        $out = ['total' => 0];
        foreach (self::STATUS_BUCKETS as $status => $key) {
            $out[$key] = (int) ($counts[$status] ?? 0);
            $out['total'] += $out[$key];
        }

        $machines = [];
        foreach ($base()->where('progress_status', '!=', 'Draft')->pluck('machine_used') as $used) {
            foreach ((array) $used as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $machines[$name] = ($machines[$name] ?? 0) + 1;
                }
            }
        }
        arsort($machines);

        $out['machines'] = collect($machines)
            ->take(8)
            ->map(fn ($count, $label) => ['label' => (string) $label, 'count' => $count])
            ->values()
            ->all();

        return $out;
    }

    /**
     * @param  array{from: string, to: string}  $period
     * @return array{0: string, 1: string}
     */
    private static function range(array $period): array
    {
        return [$period['from'].' 00:00:00', $period['to'].' 23:59:59'];
    }
}
