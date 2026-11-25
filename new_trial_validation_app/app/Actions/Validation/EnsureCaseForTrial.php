<?php

namespace App\Actions\Validation;

use App\Models\Trial;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Services\Validation\CaseNumbering;
use App\Services\Validation\TrialOutcome;
use App\Support\ValidationCaseType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Puts a trial under a Case (vd_case_links kind `trial`), creating the Case
 * when none exists for this (type, product). Idempotent: a trial that is
 * already linked keeps its Case. Never writes to trials_header.
 *
 * Grouping rule (Tahap 1): one Case per (case type, product_id). Master rows
 * that share a finish_good_code are deliberately NOT merged here; the FG
 * duplicates are reviewed by the user through the matching report first.
 */
class EnsureCaseForTrial
{
    public function __construct(private readonly CaseNumbering $numbering) {}

    public function __invoke(Trial $trial, string $source = 'manual', ?int $userId = null): ValidationCase
    {
        return DB::transaction(function () use ($trial, $source, $userId) {
            $link = ValidationCaseLink::query()
                ->where('kind', 'trial')
                ->where('ref_key', (string) $trial->id)
                ->first();

            if ($link !== null) {
                return ValidationCase::query()->findOrFail($link->case_id);
            }

            $type = ValidationCaseType::forTrial($trial->validation_category, $trial->product_type);

            $case = $trial->product_id === null
                ? null
                : ValidationCase::query()
                    ->whereNull('deleted_at')
                    ->where('case_type', $type)
                    ->where('product_id', $trial->product_id)
                    ->orderBy('id')
                    ->first();

            $case ??= $this->numbering->create(
                $type,
                ($trial->created_at ?? now())->format('ym'),
                $trial->product_id,
                (string) $trial->product_name,
                $source,
                $userId,
            );

            ValidationCaseLink::create([
                'case_id' => $case->id,
                'kind' => 'trial',
                'ref_id' => $trial->id,
                'ref_key' => (string) $trial->id,
                'linked_by' => $userId,
            ]);

            $this->renumberAttempts($case);

            return $case;
        });
    }

    /**
     * Never let Case bookkeeping break the trial workflow, and stay silent
     * until the vd_* migration has actually been run.
     */
    public static function safely(Trial $trial, ?int $userId = null): void
    {
        try {
            if (! Schema::hasTable('vd_cases') || ! Schema::hasTable('vd_case_links')) {
                return;
            }

            app(self::class)($trial, 'manual', $userId);
        } catch (Throwable $e) {
            Log::warning('EnsureCaseForTrial failed: '.$e->getMessage(), ['trial_id' => $trial->id]);
        }
    }

    /**
     * Attempt numbers on the trial links of a Case: submitted trials in
     * creation order get 1..n, Drafts (and deleted trials) get null.
     */
    public function renumberAttempts(ValidationCase $case): void
    {
        $links = ValidationCaseLink::query()
            ->where('case_id', $case->id)
            ->where('kind', 'trial')
            ->get();

        $trials = Trial::query()
            ->whereIn('id', $links->pluck('ref_id'))
            ->get()
            ->keyBy('id');

        $ordered = $trials
            ->filter(fn (Trial $t) => TrialOutcome::isAttempt($t))
            ->sortBy(fn (Trial $t) => sprintf('%s-%010d', $t->created_at?->format('YmdHis'), $t->id))
            ->keys()
            ->values();

        foreach ($links as $link) {
            $position = $ordered->search($link->ref_id);
            $attempt = $position === false ? null : $position + 1;

            if ($link->attempt_no !== $attempt) {
                $link->attempt_no = $attempt;
                $link->save();
            }
        }
    }
}
