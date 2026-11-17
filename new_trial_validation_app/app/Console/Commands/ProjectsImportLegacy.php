<?php

namespace App\Console\Commands;

use App\Services\Projects\LegacyProjectImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('projects:import-legacy
    {--dry-run : Read and report only; writes nothing, not even to the pm_* tables}
    {--connection=project_tracker : Source DB connection (read-only, see config/database.php PT_DB_*)}
    {--files-path= : Directory with the old app\'s uploaded files (default config projects.legacy_files_path)}
    {--user-map= : JSON file {"Legacy name": "email@cosmax.com"} for names that do not match a user automatically}')]
#[Description('Import the standalone Project Tracking PIE database into the pm_* tables (idempotent by legacy_id; --dry-run first).')]
class ProjectsImportLegacy extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userMap = (array) config('projects.legacy_user_map', []);
        if ($file = $this->option('user-map')) {
            $decoded = is_file((string) $file) ? json_decode((string) file_get_contents((string) $file), true) : null;
            if (! is_array($decoded)) {
                $this->error("Cannot read a JSON object from --user-map={$file}");

                return self::FAILURE;
            }
            $userMap = $decoded + $userMap;
        }

        $importer = new LegacyProjectImporter(
            (string) $this->option('connection'),
            (string) ($this->option('files-path') ?: config('projects.legacy_files_path')),
            $userMap,
        );

        try {
            $plan = $importer->plan();
        } catch (Throwable $e) {
            $this->error('Cannot read the source database: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->renderPlan($plan, $importer->existingCounts($plan), $dryRun);

        if ($dryRun) {
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        foreach (['pm_projects', 'pm_stages', 'pm_files', 'pm_improvements', 'activity_logs'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Table {$table} does not exist; run the migrations first (never migrate:fresh).");

                return self::FAILURE;
            }
        }

        try {
            $stats = $importer->apply($plan);
        } catch (Throwable $e) {
            $this->error('Import failed and was rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($stats as $key => $value) {
            $rows[] = [$key, json_encode($value)];
        }
        $this->table(['Result', 'Detail'], $rows);
        $this->info('Import finished.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, int>|null  $existing
     */
    private function renderPlan(array $plan, ?array $existing, bool $dryRun): void
    {
        $src = $plan['source'];
        $stageTypes = array_count_values(array_column($plan['stages'], 'stage_type'));
        $fileStates = array_count_values(array_merge(
            array_column($plan['files'], 'source_state'),
            array_column($plan['improvement_files'], 'source_state'),
        ));
        $fileContexts = array_count_values(array_column($plan['files'], 'context'));

        $this->table(['Entity', 'Source table', 'To import', 'Already in target'], [
            ['projects', $src['projects'], count($plan['projects']), $existing['projects'] ?? 'n/a'],
            ['stages (from metadata)', $src['project_stages'], count($plan['stages']), $existing['stages'] ?? 'n/a'],
            ['quotations (from metadata)', $src['project_quotations'], count($plan['quotations']), $existing['quotations'] ?? 'n/a'],
            ['milestones', $src['project_milestones'], count($plan['milestones']), 'n/a'],
            ['files (pm_files + pm_improvement_files)', $src['project_files'], count($plan['files']).' + '.count($plan['improvement_files']), $existing['files'] ?? 'n/a'],
            ['logs -> activity_logs', $src['project_logs'], count($plan['logs']), 'n/a'],
            ['improvements (settings blob)', '-', count($plan['improvements']), $existing['improvements'] ?? 'n/a'],
            ['stage templates', '-', count($plan['templates']), 'n/a'],
        ]);
        $this->line('Stage types: '.json_encode($stageTypes).'  File contexts: '.json_encode($fileContexts));
        $this->line('Physical files vs source dir: '.json_encode($fileStates).($dryRun ? '' : ' (missing ones are imported flagged is_missing)'));
        $this->line('Master options to ensure: '.count($plan['categories']).' categories, '.count($plan['vendors']).' vendors.');
        if ($plan['unmatched_names'] !== []) {
            $this->warn('Names kept as plain text (no matching user): '.implode(', ', $plan['unmatched_names']));
        }
        foreach ($plan['warnings'] as $warning) {
            $this->warn($warning);
        }
    }
}
