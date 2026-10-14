<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('ai:status {--max-age-hours=36 : Fail when the last ai:learn run is older than this}')]
#[Description('Read-only health check of the self-learning job: last run and active lessons per kind. Exit code 1 if stale (scheduler cron missing).')]
class AiStatus extends Command
{
    public function handle(): int
    {
        if (! Schema::hasTable('ai_learning_runs') || ! Schema::hasTable('ai_lessons')) {
            $this->error('ai_lessons tables are missing; run migrations.');

            return self::FAILURE;
        }

        foreach (DB::table('ai_lessons')->where('status', 'active')->select('kind', DB::raw('count(*) as c'))->groupBy('kind')->get() as $row) {
            $this->line(sprintf('%-22s %d active lessons', $row->kind, $row->c));
        }

        $last = DB::table('ai_learning_runs')->orderByDesc('id')->value('ran_at');
        if ($last === null) {
            $this->warn('ai:learn has never run.');

            return self::FAILURE;
        }

        $age = Carbon::parse($last)->diffInHours(Carbon::now(), true);
        $this->line("Last ai:learn run: {$last} (".round($age, 1).' h ago)');

        if ($age > (float) $this->option('max-age-hours')) {
            $this->warn('Last run is stale: is `* * * * * php artisan schedule:run` in cron for the app user?');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
