<?php

namespace App\Services\Ai;

use App\Models\AiDataFlag;
use App\Models\Trial;
use App\Models\ValidationParameter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the versioned, anonymized "trial document" (contract:
 * resources/ai/trial_document.schema.json). Read-only: it only SELECTs, so
 * it is safe against the live shared DB. The one document shape is what the
 * future chatbot tools, embeddings and training exports all consume.
 */
class TrialDocumentBuilder
{
    public function __construct(private readonly Anonymizer $anon) {}

    public static function make(): self
    {
        return new self(Anonymizer::make());
    }

    /**
     * Eager loads needed by build() — keeps the export to a handful of
     * queries per chunk.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['reviews', 'results', 'weighings', 'attachments', 'lineConfigurationReport'];
    }

    /**
     * @param  Collection<int, ValidationParameter>  $parameters  keyed by id
     * @param  Collection<int, AiDataFlag>  $flags  all flags for this trial
     * @return array<string, mixed>
     */
    public function build(Trial $trial, Collection $parameters, Collection $flags): array
    {
        $junk = $flags->where('flag', AiDataFlag::JUNK_TEXT)
            ->mapWithKeys(fn (AiDataFlag $f) => [$f->source_table.'|'.$f->source_id.'|'.$f->field => true]);
        $isJunk = fn (string $table, string|int $id, string $field): bool => $junk->has($table.'|'.$id.'|'.$field);
        $suspected = $flags->contains('flag', AiDataFlag::SUSPECTED_TEST_TRIAL);

        $product = config('ai.anonymize_product')
            ? ['product_name' => null, 'finish_good_code' => null, 'batch_number' => null, 'bulk_code' => null]
            : [
                'product_name' => $trial->product_name,
                'finish_good_code' => $trial->finish_good_code,
                'batch_number' => $trial->batch_number,
                'bulk_code' => $trial->bulk_code,
            ];

        $reason = $suspected ? null : $trial->reason;

        $results = $trial->results->map(function ($r) use ($parameters, $trial, $isJunk) {
            $param = $parameters->get($r->parameter_id);
            $id = $trial->id.':'.$r->parameter_id;

            return [
                'parameter' => $param?->parameter_name,
                'specification' => $param?->specification,
                'decision' => $r->decision,
                'result' => $isJunk('trials_results', $id, 'result_value') ? null : $r->result_value,
                'remark' => $isJunk('trials_results', $id, 'remark') ? null : $r->remark,
            ];
        })->sortBy('parameter')->values()->all();

        $reviews = $trial->reviews->sortBy(['review_round', 'department'])->map(fn ($rv) => [
            'department' => $rv->department,
            'round' => (int) $rv->review_round,
            'status' => $rv->status,
            'reviewer' => $this->anon->person($rv->reviewer_email ?: $rv->reviewer_name),
            'comment' => $isJunk('trials_review', $rv->id, 'comment') ? null : $rv->comment,
            'reviewed_at' => $rv->reviewed_at?->toIso8601String(),
            'edit_count' => (int) $rv->edit_count,
        ])->values()->all();

        $categories = $trial->attachments->whereNull('deleted_at')->countBy('category')->all();
        $line = $trial->lineConfigurationReport;

        $doc = [
            'schema_version' => config('ai.schema_version'),
            'doc_id' => 'trial-'.$trial->id,
            'trial' => $product + [
                'id' => (int) $trial->id,
                'code' => $trial->trial_code,
                'product_type' => $trial->product_type,
                'validation_category' => $trial->validation_category,
                'risk_level' => $trial->risk_level,
                'validation_date' => $trial->validation_date?->toDateString(),
                'validation_scope' => $trial->validation_scope ?? [],
                'machine_used' => $trial->machine_used ?? [],
                'estimate_qty' => $trial->estimate_qty !== null ? (float) $trial->estimate_qty : null,
                'reason' => $reason,
                'bom' => config('ai.include_bom') ? $trial->bom : null,
                'status' => $trial->progress_status,
                'current_step' => $trial->current_step,
                'final_decision' => $trial->final_decision,
                'revision_no' => (int) $trial->revision_no,
                'created_by' => $this->anon->person($trial->created_by),
                'approved_by' => $this->anon->person($trial->approved_by),
                'created_at' => $trial->created_at?->toIso8601String(),
                'approved_at' => $trial->approved_at?->toIso8601String(),
                'rejected_at' => $trial->rejected_at?->toIso8601String(),
                'approval_comment' => $trial->approval_comment,
            ],
            'results' => $results,
            'weighing' => [
                'Packaging' => $this->weighingStats($trial, 'Packaging'),
                'Filling' => $this->weighingStats($trial, 'Filling'),
            ],
            'reviews' => $reviews,
            'attachments' => ['count' => array_sum($categories), 'by_category' => $categories],
            'line_configuration' => $line ? [
                'report_date' => $line->report_date?->toDateString(),
                'total_qty' => $line->total_qty,
                'pass_qty' => $line->pass_qty,
                'ng_qty' => $line->ng_qty,
                'capacity_label' => $line->capacity_label,
                'opinion' => $line->opinion,
            ] : null,
            'quality' => [
                'suspected_test' => $suspected,
                'blanked_fields' => $junk->count(),
            ],
        ];
        $doc['retrieval_text'] = $this->retrievalText($doc);

        return $doc;
    }

    /**
     * @return array{skipped:bool, n:int, mean:float|null, min:float|null, max:float|null, std:float|null, values:array<int, float>}
     */
    private function weighingStats(Trial $trial, string $section): array
    {
        $rows = $trial->weighings->where('section', $section);
        $skipped = $rows->contains('is_skipped', true);
        $values = $skipped ? [] : $rows->whereNotNull('weight_value')->sortBy('item_no')
            ->map(fn ($w) => (float) $w->weight_value)->values()->all();
        $n = count($values);
        $mean = $n ? array_sum($values) / $n : null;
        $std = $n > 1 ? sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / ($n - 1)) : null;

        return [
            'skipped' => $skipped,
            'n' => $n,
            'mean' => $mean !== null ? round($mean, 4) : null,
            'min' => $n ? min($values) : null,
            'max' => $n ? max($values) : null,
            'std' => $std !== null ? round($std, 4) : null,
            'values' => $values,
        ];
    }

    /**
     * Plain-text rendering used as the embedding / retrieval chunk.
     *
     * @param  array<string, mixed>  $doc
     */
    private function retrievalText(array $doc): string
    {
        $t = $doc['trial'];
        $lines = [
            "Trial {$t['code']} - {$t['product_type']}".($t['product_name'] ? " ({$t['product_name']})" : '').", status {$t['status']}, risk {$t['risk_level']}, category {$t['validation_category']}.",
        ];
        if ($t['reason']) {
            $lines[] = 'Reason: '.$t['reason'];
        }
        foreach ($doc['results'] as $r) {
            if ($r['decision'] === 'NOT OK') {
                $lines[] = "NOT OK: {$r['parameter']} - result ".($r['result'] ?? '-').'; remark '.($r['remark'] ?? '-');
            }
        }
        foreach ($doc['reviews'] as $r) {
            if ($r['comment']) {
                $lines[] = "Review {$r['department']} (round {$r['round']}): {$r['comment']}";
            }
        }
        if ($t['approval_comment']) {
            $lines[] = 'Approval comment: '.$t['approval_comment'];
        }

        return implode("\n", $lines);
    }

    /** Whether flags can be read yet (the migration may not be applied on this DB). */
    public static function flagsAvailable(): bool
    {
        return Schema::hasTable('ai_data_flags');
    }
}
