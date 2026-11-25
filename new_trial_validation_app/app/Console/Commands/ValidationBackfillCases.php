<?php

namespace App\Console\Commands;

use App\Actions\Validation\EnsureCaseForTrial;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Trial;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Models\ValidationTrialMeta;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trial Validation v2.0 Tahap 1 backfill: plants a Case under every existing
 * trial (idempotent, safe to re-run), gives old trials default PM/Bulk ready
 * dates (H-1 of creation, flagged `dates_defaulted`), and links Projects to
 * Cases where that is unambiguous. Ambiguous rows are only printed, never
 * guessed. Writes only vd_* tables: never trials_header, never stage status.
 *
 * --dry-run runs the exact same code inside a transaction that is rolled
 * back, so the printed numbers are real and nothing is persisted.
 */
class ValidationBackfillCases extends Command
{
    protected $signature = 'validation:backfill-cases {--dry-run : Run everything, then roll back (writes nothing)}';

    protected $description = 'Backfill Validation Cases, default ready dates and project links from existing trials';

    public function handle(EnsureCaseForTrial $ensure): int
    {
        foreach (['vd_cases', 'vd_case_counters', 'vd_case_links', 'vd_trial_meta'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Table {$table} does not exist. Run the vd_* migration first.");

                return self::FAILURE;
            }
        }

        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'DRY RUN: everything below is rolled back at the end.' : 'LIVE RUN: writing vd_* tables.');

        DB::beginTransaction();

        try {
            $this->backfill($ensure);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $dry ? DB::rollBack() : DB::commit();

        $this->newLine();
        $this->info($dry ? 'Rolled back. Nothing was written.' : 'Committed.');

        return self::SUCCESS;
    }

    private function backfill(EnsureCaseForTrial $ensure): void
    {
        $casesBefore = ValidationCase::query()->count();
        $linksBefore = ValidationCaseLink::query()->where('kind', 'trial')->count();

        $trials = Trial::query()->whereNull('deleted_at')->orderBy('created_at')->orderBy('id')->get();

        $metaCreated = 0;
        $noProduct = [];
        foreach ($trials as $trial) {
            $ensure($trial, 'backfill');

            if ($trial->product_id === null) {
                $noProduct[] = [$trial->id, $trial->trial_code, $trial->product_name];
            }

            if (! ValidationTrialMeta::query()->where('trial_id', $trial->id)->exists()) {
                $default = ($trial->created_at ?? now())->copy()->subDay()->toDateString();
                ValidationTrialMeta::create([
                    'trial_id' => $trial->id,
                    'pm_ready_date' => $default,
                    'bulk_ready_date' => $default,
                    'dates_defaulted' => true,
                ]);
                $metaCreated++;
            }
        }

        $casesAfter = ValidationCase::query()->count();
        $linksAfter = ValidationCaseLink::query()->where('kind', 'trial')->count();

        $this->newLine();
        $this->line("Trials scanned:        {$trials->count()}");
        $this->line('New Cases:             '.($casesAfter - $casesBefore).' (total '.$casesAfter.')');
        $this->line('New trial links:       '.($linksAfter - $linksBefore));
        $this->line("Default-date rows:     {$metaCreated}");

        $byType = ValidationCase::query()->selectRaw('case_type, COUNT(*) as n')->groupBy('case_type')->pluck('n', 'case_type');
        $this->line('Cases per type:        '.$byType->map(fn ($n, $t) => "{$t}={$n}")->implode(', '));

        $reAttempts = DB::table('vd_case_links')->where('kind', 'trial')->whereNotNull('attempt_no')
            ->selectRaw('case_id, COUNT(*) as n')->groupBy('case_id')->havingRaw('COUNT(*) > 1')->get();
        $this->line('Cases with 2+ attempts: '.$reAttempts->count());

        if ($noProduct !== []) {
            $this->warn('Trials without product_id (own Case each, review them):');
            $this->table(['id', 'trial_code', 'product_name'], $noProduct);
        }

        $this->projectLinks();
        $this->reportFgDuplicates();
        $this->reportStaleSnapshots($trials);
    }

    private function projectLinks(): void
    {
        $linked = 0;
        $report = [];

        // 1) A project stage already linked to a trial ties its project to that trial's Case.
        $stages = ProjectStage::query()->whereNotNull('trial_id')->get(['id', 'project_id', 'trial_id']);
        foreach ($stages as $stage) {
            $caseId = ValidationCaseLink::query()->where('kind', 'trial')->where('ref_key', (string) $stage->trial_id)->value('case_id');
            if ($caseId === null) {
                continue;
            }

            $linked += $this->linkProject((int) $stage->project_id, (int) $caseId, 'via stage', $report) ? 1 : 0;
        }

        // 2) A project with a product links only when that product has exactly one Case.
        $projects = Project::query()->whereNull('deleted_at')->whereNotNull('product_id')->get(['id', 'code', 'name', 'product_id']);
        foreach ($projects as $project) {
            $caseIds = ValidationCase::query()->whereNull('deleted_at')->where('product_id', $project->product_id)->pluck('id');

            if ($caseIds->count() === 1) {
                $linked += $this->linkProject((int) $project->id, (int) $caseIds->first(), 'via product', $report) ? 1 : 0;
            } elseif ($caseIds->count() > 1) {
                $report[] = [$project->code, $project->name, 'AMBIGUOUS: product has '.$caseIds->count().' Cases'];
            }
        }

        $this->newLine();
        $this->line("Project links created: {$linked} (stages/status are never touched)");
        if ($report !== []) {
            $this->warn('Project <-> Case items to review (not linked automatically):');
            $this->table(['project', 'name', 'note'], $report);
        }

        // 3) Name-only candidates: shown, never linked.
        $candidates = [];
        $unlinked = Project::query()->whereNull('deleted_at')->get(['id', 'code', 'name'])
            ->reject(fn (Project $p) => ValidationCaseLink::query()->where('kind', 'project')->where('ref_key', (string) $p->id)->exists());
        $cases = ValidationCase::query()->whereNull('deleted_at')->get(['id', 'case_no', 'product_name', 'product_id'])
            ->map(fn (ValidationCase $c) => [$c, $this->normalize((string) ($c->product->product_name ?? $c->product_name))]);
        foreach ($unlinked as $project) {
            $needle = $this->normalize($project->name);
            if ($needle === '') {
                continue;
            }
            foreach ($cases as [$case, $name]) {
                if ($name === $needle) {
                    $candidates[] = [$project->code, $project->name, $case->case_no];
                }
            }
        }
        if ($candidates !== []) {
            $this->warn('Exact-name candidates (confirm manually, NOT linked):');
            $this->table(['project', 'name', 'case'], $candidates);
        }
    }

    /**
     * @param  list<array<int, string>>  $report
     */
    private function linkProject(int $projectId, int $caseId, string $how, array &$report): bool
    {
        $existing = ValidationCaseLink::query()->where('kind', 'project')->where('ref_key', (string) $projectId)->first();

        if ($existing !== null) {
            if ($existing->case_id !== $caseId) {
                $p = Project::query()->find($projectId);
                $report[] = [$p->code ?? (string) $projectId, $p->name ?? '', "CONFLICT ({$how}): already linked to another Case"];
            }

            return false;
        }

        ValidationCaseLink::create([
            'case_id' => $caseId,
            'kind' => 'project',
            'ref_id' => $projectId,
            'ref_key' => (string) $projectId,
        ]);

        return true;
    }

    private function normalize(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)));
    }

    /** Matching report: master products sharing a finish_good_code (user reviews, nothing is merged). */
    private function reportFgDuplicates(): void
    {
        $usedProductIds = ValidationCase::query()->whereNotNull('product_id')->pluck('product_id')->unique();

        $groups = Product::query()
            ->whereNull('deleted_at')
            ->whereIn('id', $usedProductIds)
            ->get(['id', 'product_name', 'finish_good_code'])
            ->groupBy(fn (Product $p) => trim((string) $p->finish_good_code))
            ->filter(fn ($g) => $g->count() > 1);

        $this->newLine();
        $this->line('Duplicate FG-code groups among Case products: '.$groups->count().' ('.$groups->flatten(1)->count().' master rows). Cases stay one per product_id.');
        $rows = [];
        foreach ($groups->take(60) as $code => $items) {
            $rows[] = [$code === '' ? '(empty)' : $code, $items->count(), $items->pluck('product_name')->take(3)->implode(' | ')];
        }
        if ($rows !== []) {
            $this->table(['fg_code', 'rows', 'products (first 3)'], $rows);
        }
    }

    /**
     * @param  Collection<int, Trial>  $trials
     */
    private function reportStaleSnapshots($trials): void
    {
        $products = Product::query()->whereIn('id', $trials->pluck('product_id')->filter()->unique())->get()->keyBy('id');

        $rows = [];
        foreach ($trials as $trial) {
            $master = $products->get($trial->product_id);
            if ($master !== null && trim((string) $trial->finish_good_code) !== trim((string) $master->finish_good_code)) {
                $rows[] = [$trial->id, $trial->trial_code, (string) $trial->finish_good_code, (string) $master->finish_good_code];
            }
        }

        $this->newLine();
        $this->line('Trials with a stale FG snapshot (Journey reads the master FG): '.count($rows));
        if ($rows !== []) {
            $this->table(['id', 'trial_code', 'snapshot FG', 'master FG'], $rows);
        }
    }
}
