<?php

namespace App\Console\Commands;

use App\Models\AiDataFlag;
use App\Services\Ai\DataQualityScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Data-quality report for the AI-readiness effort. Read-only by default;
 * --apply only INSERTs rows into the new ai_data_flags side table (it never
 * touches trials_*), so it is safe to run against the live shared DB.
 */
#[Signature('ai:quality-scan {--apply : Persist findings as ai_data_flags (default is report only)}')]
#[Description('Report junk/test free-text in trial data and optionally flag it for the AI export')]
class AiQualityScan extends Command
{
    public function handle(DataQualityScanner $scanner): int
    {
        $result = $scanner->scan();

        $this->table(
            ['column', 'rows', 'with text', 'junk'],
            collect($result['totals'])->map(fn ($t, $k) => [$k, $t['rows'], $t['with_text'], $t['junk']])->values()->all(),
        );

        $findings = $result['findings'];
        $this->newLine();
        $this->info(count($findings).' flagged value(s). Samples:');
        $this->table(
            ['table', 'id', 'field', 'flag', 'why', 'text'],
            collect($findings)->take(15)->map(fn ($f) => [$f['table'], $f['source_id'], $f['field'], $f['flag'], $f['reason'], $f['text']])->all(),
        );

        if (! $this->option('apply')) {
            $this->comment('Dry run - nothing written. Re-run with --apply to save the flags.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('ai_data_flags')) {
            $this->error('Table ai_data_flags does not exist yet. Run the pending migration first.');

            return self::FAILURE;
        }

        foreach ($findings as $f) {
            AiDataFlag::query()->firstOrCreate(
                ['source_table' => $f['table'], 'source_id' => $f['source_id'], 'field' => $f['field'], 'flag' => $f['flag']],
                ['trial_id' => $f['trial_id'], 'reason' => $f['reason'], 'detected_by' => 'rule'],
            );
        }
        $this->info('Saved '.count($findings).' flag(s).');

        return self::SUCCESS;
    }
}
