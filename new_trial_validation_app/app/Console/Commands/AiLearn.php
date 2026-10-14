<?php

namespace App\Console\Commands;

use App\Services\Ai\WeighingBaselineLearner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('ai:learn')]
#[Description('Self-learning pass (shadow mode): derive lessons from trial data into ai_lessons. Reads app tables only.')]
class AiLearn extends Command
{
    public function handle(WeighingBaselineLearner $learner): int
    {
        if (! Schema::hasTable('ai_lessons') || ! Schema::hasTable('ai_learning_runs')) {
            $this->error('ai_lessons tables are missing; run the 2026_10_14_000002 migration first.');

            return self::FAILURE;
        }

        $start = microtime(true);
        $stats = $learner->learn();
        $ms = (int) round((microtime(true) - $start) * 1000);

        DB::table('ai_learning_runs')->insert($stats + ['duration_ms' => $ms, 'ran_at' => now()]);

        $this->table(array_keys($stats), [array_values($stats)]);
        $this->info("Done in {$ms} ms (shadow mode: nothing is shown or acted on).");

        return self::SUCCESS;
    }
}
