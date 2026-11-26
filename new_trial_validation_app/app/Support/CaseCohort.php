<?php

namespace App\Support;

use App\Models\ValidationCase;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Brief 03 data cohort: a Case whose real start is on/after the cutoff is
 * "eligible", an earlier one is "historical" (shown, never counted in the new
 * KPIs). The start is the earliest linked trial's created_at, NOT the Case
 * row's own created_at: backfilled Cases are created "now" but describe old
 * trials. A Case with no trial yet (a fresh Assessment) uses its own date.
 */
final class CaseCohort
{
    public const ELIGIBLE = 'eligible';

    public const HISTORICAL = 'historical';

    public static function cutoff(): Carbon
    {
        return Carbon::parse((string) config('validation_v2.cohort_start', '2026-11-16'))->startOfDay();
    }

    /** SQL expression for a Case's start date, for queries on vd_cases. */
    /**
     * @param  'vd_cases'|'c'  $caseTable
     * @return literal-string
     */
    public static function startSql(string $caseTable = 'vd_cases'): string
    {
        return $caseTable === 'c'
            ? "COALESCE((SELECT MIN(t.created_at) FROM vd_case_links l JOIN trials_header t ON t.id = l.ref_id WHERE l.case_id = c.id AND l.kind = 'trial'), c.created_at)"
            : "COALESCE((SELECT MIN(t.created_at) FROM vd_case_links l JOIN trials_header t ON t.id = l.ref_id WHERE l.case_id = vd_cases.id AND l.kind = 'trial'), vd_cases.created_at)";
    }

    public static function startOf(ValidationCase $case): ?CarbonInterface
    {
        $value = DB::table('vd_case_links as l')
            ->join('trials_header as t', 't.id', '=', 'l.ref_id')
            ->where('l.case_id', $case->id)
            ->where('l.kind', 'trial')
            ->min('t.created_at');

        return $value !== null ? Carbon::parse((string) $value) : $case->created_at;
    }

    public static function forCase(ValidationCase $case): string
    {
        $start = self::startOf($case);

        return $start !== null && $start->greaterThanOrEqualTo(self::cutoff()) ? self::ELIGIBLE : self::HISTORICAL;
    }

    /** Restrict a vd_cases query to one cohort ('' leaves it untouched). */
    public static function scope(BuilderContract $query, string $cohort): void
    {
        if ($cohort === self::ELIGIBLE) {
            $query->whereRaw(self::startSql().' >= ?', [self::cutoff()->toDateTimeString()]);
        } elseif ($cohort === self::HISTORICAL) {
            $query->whereRaw(self::startSql().' < ?', [self::cutoff()->toDateTimeString()]);
        }
    }
}
