<?php

namespace App\Actions\Validation;

use App\Models\Improvement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Gives an Improvement its own number IMP-{YYYY}-{seq 3 digits}. The number
 * lives in vd_improvement_numbers (never a new column on pm_improvements);
 * the sequence comes from a locked vd_number_counters row per year, not
 * MAX()+1. Idempotent: an Improvement keeps the number it already has.
 */
class AssignImprovementNumber
{
    public function __invoke(Improvement $improvement): string
    {
        return DB::transaction(function () use ($improvement) {
            $existing = DB::table('vd_improvement_numbers')->where('improvement_id', $improvement->id)->value('number');
            if ($existing !== null) {
                return (string) $existing;
            }

            $year = (int) ($improvement->submitted_at ?? now())->format('Y');
            $now = now();

            DB::table('vd_number_counters')->insertOrIgnore([
                'scope' => 'improvement',
                'period' => (string) $year,
                'last_seq' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $row = DB::table('vd_number_counters')
                ->where('scope', 'improvement')
                ->where('period', (string) $year)
                ->lockForUpdate()
                ->first();

            $seq = (int) $row->last_seq + 1;
            DB::table('vd_number_counters')->where('id', $row->id)->update(['last_seq' => $seq, 'updated_at' => $now]);

            $number = sprintf('IMP-%d-%03d', $year, $seq);

            DB::table('vd_improvement_numbers')->insert([
                'improvement_id' => $improvement->id,
                'number' => $number,
                'year' => $year,
                'seq' => $seq,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $number;
        });
    }

    /** Never break the Improvement flow; silent until the vd_* migration has run. */
    public static function safely(Improvement $improvement): void
    {
        try {
            if (! Schema::hasTable('vd_improvement_numbers') || ! Schema::hasTable('vd_number_counters')) {
                return;
            }

            app(self::class)($improvement);
        } catch (Throwable $e) {
            Log::warning('AssignImprovementNumber failed: '.$e->getMessage(), ['improvement_id' => $improvement->id]);
        }
    }

    /**
     * @param  iterable<int, int>  $ids
     * @return array<int, string> improvement id => number
     */
    public static function numbersFor(iterable $ids): array
    {
        if (! Schema::hasTable('vd_improvement_numbers')) {
            return [];
        }

        return DB::table('vd_improvement_numbers')
            ->whereIn('improvement_id', collect($ids)->all())
            ->pluck('number', 'improvement_id')
            ->map(fn ($n) => (string) $n)
            ->all();
    }
}
