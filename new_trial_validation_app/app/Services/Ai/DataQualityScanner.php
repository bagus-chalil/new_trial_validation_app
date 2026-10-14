<?php

namespace App\Services\Ai;

use App\Models\AiDataFlag;
use Illuminate\Support\Facades\DB;

/**
 * Read-only scan of the free-text columns that would feed an AI. Returns
 * findings; persisting them as ai_data_flags is the caller's decision.
 */
class DataQualityScanner
{
    /**
     * @return array{findings: list<array{table:string,source_id:string,field:string,trial_id:int,flag:string,reason:string,text:string}>, totals: array<string, array{rows:int, with_text:int, junk:int}>}
     */
    public function scan(): array
    {
        $findings = [];
        $totals = [];

        // [table, free-text field, source_id resolver, trial_id resolver]
        $targets = [
            ['trials_header', 'reason', fn ($r) => (string) $r->id, fn ($r) => (int) $r->id],
            ['trials_review', 'comment', fn ($r) => (string) $r->id, fn ($r) => (int) $r->trial_id],
            ['trials_results', 'remark', fn ($r) => $r->trial_id.':'.$r->parameter_id, fn ($r) => (int) $r->trial_id],
            ['trials_results', 'result_value', fn ($r) => $r->trial_id.':'.$r->parameter_id, fn ($r) => (int) $r->trial_id],
        ];

        foreach ($targets as [$table, $field, $sourceId, $trialId]) {
            $key = $table.'.'.$field;
            $rows = $withText = $junk = 0;

            $query = DB::table($table);
            if ($table === 'trials_header') {
                $query->whereNull('deleted_at');
            }
            if ($table === 'trials_review') {
                $query->where('status', 'Reviewed');
            }

            foreach ($query->cursor() as $row) {
                $rows++;
                $text = $row->{$field} ?? null;
                if (trim((string) $text) === '') {
                    continue;
                }
                $withText++;

                $reason = TextQuality::junkReason((string) $text);
                if ($reason === null) {
                    continue;
                }
                $junk++;
                $findings[] = [
                    'table' => $table,
                    'source_id' => $sourceId($row),
                    'field' => $field,
                    'trial_id' => $trialId($row),
                    'flag' => ($table === 'trials_header')
                        ? AiDataFlag::SUSPECTED_TEST_TRIAL
                        : AiDataFlag::JUNK_TEXT,
                    'reason' => $reason,
                    'text' => mb_substr((string) $text, 0, 60),
                ];
            }

            $totals[$key] = ['rows' => $rows, 'with_text' => $withText, 'junk' => $junk];
        }

        return ['findings' => $findings, 'totals' => $totals];
    }
}
