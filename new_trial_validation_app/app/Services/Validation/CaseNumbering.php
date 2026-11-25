<?php

namespace App\Services\Validation;

use App\Models\ValidationCase;
use App\Support\ValidationCaseType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Case number generator: VAL-{NPD|IMP|MIX}-{YYMM}-{seq 3 digits}. The
 * sequence comes from a locked counter row per (type, period), never
 * MAX()+1, so two concurrent creations cannot collide (the lesson from
 * CreateTrial's trial_code race). A unique index on case_no is the backstop.
 */
class CaseNumbering
{
    /**
     * Reserve the next number. Call inside the transaction that inserts the
     * Case, so a failed insert does not burn a number.
     *
     * @return array{case_no: string, seq: int}
     */
    public function next(string $type, string $period): array
    {
        if (! in_array($type, ValidationCaseType::ALL, true)) {
            throw new InvalidArgumentException("Unknown case type [{$type}].");
        }

        if (preg_match('/^\d{4}$/', $period) !== 1) {
            throw new InvalidArgumentException("Period must be YYMM, got [{$period}].");
        }

        return DB::transaction(function () use ($type, $period) {
            $now = now();

            // Make sure the row exists, then lock it for the increment.
            DB::table('vd_case_counters')->insertOrIgnore([
                'case_type' => $type,
                'period' => $period,
                'last_seq' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $row = DB::table('vd_case_counters')
                ->where('case_type', $type)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $seq = (int) $row->last_seq + 1;

            DB::table('vd_case_counters')
                ->where('id', $row->id)
                ->update(['last_seq' => $seq, 'updated_at' => $now]);

            return ['case_no' => $this->format($type, $period, $seq), 'seq' => $seq];
        });
    }

    public function format(string $type, string $period, int $seq): string
    {
        return sprintf('VAL-%s-%s-%03d', $type, $period, $seq);
    }

    /** Create a Case with a fresh number. */
    public function create(string $type, string $period, ?int $productId, string $productName, string $source, ?int $userId = null): ValidationCase
    {
        return DB::transaction(function () use ($type, $period, $productId, $productName, $source, $userId) {
            ['case_no' => $caseNo, 'seq' => $seq] = $this->next($type, $period);

            return ValidationCase::create([
                'case_no' => $caseNo,
                'case_type' => $type,
                'period' => $period,
                'seq' => $seq,
                'product_id' => $productId,
                'product_name' => $productName,
                'source' => $source,
                'created_by_user_id' => $userId,
            ]);
        });
    }
}
