<?php

namespace App\Console\Commands;

use App\Actions\Vision\RecordStartupAiFeedback;
use App\Models\IpcAiFieldFeedback;
use App\Models\IpcBatch;
use Illuminate\Console\Command;

/**
 * Startup AI accuracy per field, from `ipc_ai_field_feedback` (AI read vs the value the operator
 * finally saved). Read-only except `--backfill`, which only inserts feedback rows for batches
 * that were saved before the table existed. The number that matters most is
 * "trusted tapi salah": the AI passed its own gate yet disagreed with the saved value.
 */
class VisionAccuracy extends Command
{
    protected $signature = 'vision:accuracy
        {--from= : Only batches created on/after this date (YYYY-MM-DD)}
        {--to= : Only batches created on/before this date (YYYY-MM-DD)}
        {--backfill : First build feedback rows from existing ipc_logs for completed Startup batches}
        {--json : Print JSON instead of a table}';

    protected $description = 'Startup AI accuracy per field (AI read vs final operator value)';

    public function handle(RecordStartupAiFeedback $recorder): int
    {
        if ($this->option('backfill')) {
            $done = 0;
            IpcBatch::query()
                ->whereHas('startupCheck', fn ($q) => $q->whereNotNull('completed_at'))
                ->whereHas('ipcLogs')
                ->each(function (IpcBatch $batch) use ($recorder, &$done) {
                    $done += $recorder->handle($batch, [], backfill: true);
                });
            $this->info("Backfill: {$done} baris feedback ditulis/diperbarui.");
        }

        $query = IpcAiFieldFeedback::query()
            ->when($this->option('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->option('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        $rows = [];
        foreach ($query->get()->groupBy('field_key') as $key => $group) {
            // Only operator-confirmed values are ground truth: a copied suggestion equals the AI by construction.
            $truth = $group->whereIn('source', ['typed', 'edited_after_suggestion']);
            $trusted = $truth->where('ai_trusted', true);
            $untrusted = $truth->where('ai_trusted', false);
            $rows[] = [
                'field' => $key,
                'total' => $group->count(),
                'ground_truth' => $truth->count(),
                'unknown_source' => $group->where('source', 'unknown')->count(),
                'suggestion_used' => $group->where('source', 'suggestion_applied')->count(),
                'trusted_correct' => $trusted->where('outcome', 'match')->count(),
                'trusted_wrong' => $trusted->where('outcome', 'mismatch')->count(),
                'untrusted_correct' => $untrusted->where('outcome', 'match')->count(),
                'unreadable' => $truth->where('outcome', 'ai_unreadable')->count(),
                'edited_after_suggestion' => $group->where('source', 'edited_after_suggestion')->count(),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }
        if ($rows === []) {
            $this->warn('Belum ada data feedback. Jalankan dengan --backfill atau tunggu Startup disimpan setelah migrasi.');

            return self::SUCCESS;
        }
        $this->table(array_keys($rows[0]), $rows);
        $this->line('trusted_wrong = AI lolos gerbang kepercayaan tapi beda dari nilai simpan (target 0). unknown_source = batch lama, tak diketahui memakai saran atau tidak, tidak dihitung sebagai kebenaran.');

        return self::SUCCESS;
    }
}
