<?php

namespace App\Console\Commands;

use App\Models\AiDataFlag;
use App\Models\Trial;
use App\Models\ValidationParameter;
use App\Services\Ai\TrialDocumentBuilder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Writes one versioned JSONL file of anonymized trial documents plus a
 * manifest (counts + sha256) to the private local disk. Strictly read-only
 * on the database.
 */
#[Signature('ai:export {--include-suspected : Also export trials flagged as suspected test data} {--only-final : Only Approved/Rejected trials} {--path= : Output directory on the local disk}')]
#[Description('Export anonymized, versioned trial documents (JSONL) for AI use')]
class AiExport extends Command
{
    public function handle(): int
    {
        $builder = TrialDocumentBuilder::make();
        $parameters = ValidationParameter::query()->get()->keyBy('id');
        $flagsAvailable = TrialDocumentBuilder::flagsAvailable();

        $suspectedIds = $flagsAvailable
            ? AiDataFlag::query()->where('flag', AiDataFlag::SUSPECTED_TEST_TRIAL)->pluck('trial_id')->all()
            : [];

        $dir = trim((string) ($this->option('path') ?: config('ai.export_disk_path')), '/');
        $stamp = now()->format('Ymd-His');
        $file = "{$dir}/trials-{$stamp}.jsonl";
        $disk = Storage::disk('local');
        $disk->makeDirectory($dir);
        $handle = fopen($disk->path($file), 'wb');
        if ($handle === false) {
            $this->error("Cannot open {$file} for writing.");

            return self::FAILURE;
        }

        $exported = 0;
        $skipped = 0;
        $includeSuspected = (bool) $this->option('include-suspected');

        Trial::query()
            ->whereNull('deleted_at')
            ->when($this->option('only-final'), fn ($q) => $q->whereIn('progress_status', ['Approved', 'Rejected']))
            ->with(TrialDocumentBuilder::relations())
            ->orderBy('id')
            ->chunkById(50, function ($trials) use (&$exported, &$skipped, $builder, $parameters, $flagsAvailable, $suspectedIds, $includeSuspected, $handle) {
                $flags = $flagsAvailable
                    ? AiDataFlag::query()->whereIn('trial_id', $trials->pluck('id'))->get()->groupBy('trial_id')
                    : collect();

                foreach ($trials as $trial) {
                    if (! $includeSuspected && in_array($trial->id, $suspectedIds, true)) {
                        $skipped++;

                        continue;
                    }
                    $doc = $builder->build($trial, $parameters, $flags->get($trial->id, collect()));
                    fwrite($handle, json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                    $exported++;
                }
            });

        fclose($handle);

        $manifest = [
            'schema_version' => config('ai.schema_version'),
            'generated_at' => now()->toIso8601String(),
            'file' => basename($file),
            'documents' => $exported,
            'skipped_suspected_test' => $skipped,
            'flags_table_available' => $flagsAvailable,
            'sha256' => hash_file('sha256', $disk->path($file)),
        ];
        $disk->put("{$dir}/manifest-{$stamp}.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->info("Exported {$exported} document(s) ({$skipped} skipped) -> storage/app/private/{$file}");
        if (! $flagsAvailable) {
            $this->warn('ai_data_flags table not found: junk text was NOT blanked. Apply the migration and run ai:quality-scan --apply first for a clean export.');
        }

        return self::SUCCESS;
    }
}
