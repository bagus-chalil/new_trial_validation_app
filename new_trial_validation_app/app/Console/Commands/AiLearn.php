<?php

namespace App\Console\Commands;

use App\Services\Ai\NotOkPatternLearner;
use App\Services\Ai\StepDurationLearner;
use App\Services\Ai\WeighingBaselineLearner;
use App\Services\Ai\WeighingConsistencyLearner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('ai:learn')]
#[Description('Self-learning pass (shadow mode): derive lessons from trial data into ai_lessons. Reads app tables only.')]
class AiLearn extends Command
{
    public function handle(WeighingBaselineLearner $baseline, WeighingConsistencyLearner $consistency, NotOkPatternLearner $notOk, StepDurationLearner $duration): int
    {
        if (! Schema::hasTable('ai_lessons') || ! Schema::hasTable('ai_learning_runs')) {
            $this->error('ai_lessons tables are missing; run the 2026_10_14_000002 migration first.');

            return self::FAILURE;
        }

        $start = microtime(true);
        $results = ['baseline' => $baseline->learn(), 'consistency' => $consistency->learn(), 'not_ok' => $notOk->learn(), 'duration' => $duration->learn()];
        $ms = (int) round((microtime(true) - $start) * 1000);

        $totals = array_fill_keys(['created', 'updated', 'unchanged', 'revoked', 'insufficient', 'excluded_trials'], 0);
        foreach ($results as $learner => $stats) {
            foreach ($stats as $key => $value) {
                $totals[$key] += $value;
            }
            $this->line(sprintf('%-12s %s', $learner, json_encode($stats)));
        }

        DB::table('ai_learning_runs')->insert($totals + ['duration_ms' => $ms, 'ran_at' => now()]);

        $this->info("Done in {$ms} ms (shadow mode: nothing is shown or acted on).");

        return self::SUCCESS;
    }
}
