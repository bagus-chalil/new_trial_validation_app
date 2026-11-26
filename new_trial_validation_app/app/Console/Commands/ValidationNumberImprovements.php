<?php

namespace App\Console\Commands;

use App\Actions\Validation\AssignImprovementNumber;
use App\Models\Improvement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trial Validation v2.0 Tahap 2: gives every existing Improvement its own
 * number IMP-{YYYY}-{seq} (oldest first, so numbers follow submission order).
 * Idempotent: an Improvement that already has a number keeps it. Writes only
 * vd_improvement_numbers / vd_number_counters, never pm_improvements.
 * --dry-run runs the same code inside a transaction that is rolled back.
 */
class ValidationNumberImprovements extends Command
{
    protected $signature = 'validation:number-improvements {--dry-run : Run everything, then roll back (writes nothing)}';

    protected $description = 'Assign IMP-YYYY-NNN numbers to Improvements that have none yet';

    public function handle(AssignImprovementNumber $assign): int
    {
        foreach (['pm_improvements', 'vd_improvement_numbers', 'vd_number_counters'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Table {$table} does not exist. Run the migrations first.");

                return self::FAILURE;
            }
        }

        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'DRY RUN: rolled back at the end.' : 'LIVE RUN: writing vd_* tables.');

        DB::beginTransaction();

        try {
            $numbered = 0;
            $kept = 0;

            Improvement::query()
                ->whereNull('deleted_at')
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->each(function (Improvement $improvement) use ($assign, &$numbered, &$kept) {
                    $had = DB::table('vd_improvement_numbers')->where('improvement_id', $improvement->id)->exists();
                    $number = $assign($improvement);
                    $had ? $kept++ : $numbered++;
                    $this->line(($had ? '  kept  ' : '  new   ').$number.'  #'.$improvement->id.'  '.$improvement->title);
                });
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $dry ? DB::rollBack() : DB::commit();

        $this->newLine();
        $this->info("Numbered {$numbered}, already numbered {$kept}.".($dry ? ' Rolled back, nothing written.' : ''));

        return self::SUCCESS;
    }
}
