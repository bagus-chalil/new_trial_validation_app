<?php

namespace App\Actions\Vision;

use App\Models\IpcAiFieldFeedback;
use App\Models\IpcBatch;
use App\Models\IpcLog;

/**
 * Called after a Startup Check is saved: pairs every AI read of this batch's Startup photos with the
 * value the operator finally saved, so accuracy can be measured later (`php artisan vision:accuracy`).
 * Measurement only: it never changes the batch and never throws into the save (the caller catches).
 *
 * @param  list<string>  $applied  form fields whose "Pakai saran" button was used (mixing_date, shelf_life_months)
 */
class RecordStartupAiFeedback
{
    /** field_key => [field_type, form field that "Pakai saran" fills]. */
    private const SPECS = [
        'mixing_date' => ['label_bulk', 'mixing_date'],
        'no_batch' => ['label_bulk', null],
        'shelf_life' => ['im_document', 'shelf_life_months'],
        'coding_batch' => ['coding_print', null],
        'coding_exp' => ['coding_print', null],
    ];

    /** @param bool $backfill batches saved before this existed: whether "Pakai saran" was used is unknown */
    private bool $backfill = false;

    public function handle(IpcBatch $batch, array $applied = [], bool $backfill = false): int
    {
        $this->backfill = $backfill;
        $batch->refresh();
        $written = 0;
        $tubeType = config('vision.startup_ai.tube_llm_field_type');

        foreach (self::SPECS as $key => [$type, $formField]) {
            $log = $this->latestLog($batch, $type);
            if ($log === null) {
                continue;
            }
            [$ai, $final, $trusted] = $this->pair($key, $log, $batch);
            $written += $this->store($batch, $log, $key, $type, $ai, $trusted, $final, $formField, $applied) ? 1 : 0;
        }

        if ($tubeType && ($log = $this->latestLog($batch, $tubeType)) !== null) {
            $raw = $log->vision_response ?? [];
            $ai = $raw['exp_raw'] ?? null;
            $final = $batch->exp_date?->format('dmy');
            $trusted = ($log->vision_status === 'OK') && $ai !== null;
            $written += $this->store($batch, $log, 'tube_exp', $tubeType, $ai, $trusted, $final, null, $applied) ? 1 : 0;
        }

        return $written;
    }

    private function latestLog(IpcBatch $batch, string $type): ?IpcLog
    {
        return IpcLog::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', IpcBatch::STAGE_STARTUP)
            ->where('field_type', $type)
            ->whereIn('vision_status', ['OK', 'LOW_CONFIDENCE'])
            // A blank photo never reached the AI: it says nothing about AI accuracy.
            ->where(fn ($q) => $q->whereNull('engine_used')->orWhere('engine_used', '!=', ReadStartupPhoto::PRECHECK_ENGINE))
            ->latest('id')
            ->first();
    }

    /** @return array{0: ?string, 1: ?string, 2: bool} ai value, final value, trusted */
    private function pair(string $key, IpcLog $log, IpcBatch $batch): array
    {
        $raw = $log->vision_response ?? [];
        $flag = fn (string $k, ?string $map = null): bool => $map !== null && isset($raw[$map][$k])
            ? (bool) $raw[$map][$k]
            : ((bool) ($raw['legible'][$k] ?? false) && (bool) ($raw['format_ok'][$k] ?? false) && (bool) ($raw['view_agreement'][$k] ?? false));

        return match ($key) {
            'mixing_date' => [
                $this->labelDateToIso($raw['mixing_date_raw'] ?? null),
                $batch->mixing_date?->format('Y-m-d'),
                $flag('mixing_date'),
            ],
            'no_batch' => [
                $this->normBatch($raw['no_batch_raw'] ?? null),
                $this->normBatch($batch->no_batch),
                $flag('no_batch'),
            ],
            'shelf_life' => [
                isset($raw['shelf_life_value']) ? (string) (((int) ($raw['shelf_life_value']['years'] ?? 0) * 12) + (int) ($raw['shelf_life_value']['months'] ?? 0)) : null,
                $batch->shelf_life_months !== null ? (string) $batch->shelf_life_months : null,
                $flag('shelf_life'),
            ],
            'coding_batch' => [
                isset($raw['batch_raw']) ? strtoupper(trim((string) $raw['batch_raw'])) : null,
                $this->batchLetters($batch->no_batch),
                $flag('batch', 'trusted'),
            ],
            'coding_exp' => $this->codingExp($raw, $batch, $flag('exp', 'trusted')),
        };
    }

    /** @return array{0: ?string, 1: ?string, 2: bool} */
    private function codingExp(array $raw, IpcBatch $batch, bool $trusted): array
    {
        $parts = $raw['exp_parts'] ?? null;
        $format = $raw['exp_format'] ?? null;
        if (! is_array($parts) || $batch->exp_date === null) {
            return [null, $batch->exp_date?->format('dmy'), $trusted];
        }
        if ($format === 'MM_YYYY') {
            return [sprintf('%02d/%04d', (int) ($parts['month'] ?? 0), (int) ($parts['year'] ?? 0)), $batch->exp_date->format('m/Y'), $trusted];
        }

        return [($parts['day'] ?? '').($parts['month'] ?? '').($parts['year'] ?? ''), $batch->exp_date->format('dmy'), $trusted];
    }

    private function store(IpcBatch $batch, IpcLog $log, string $key, string $type, ?string $ai, bool $trusted, ?string $final, ?string $formField, array $applied): bool
    {
        if ($final === null) {
            return false; // nothing saved to compare against
        }

        $source = 'typed';
        if ($this->backfill && $formField !== null) {
            $source = 'unknown'; // could have been typed or copied from the suggestion
        } elseif ($formField !== null && in_array($formField, $applied, true)) {
            $source = $ai !== null && $ai === $final ? 'suggestion_applied' : 'edited_after_suggestion';
        }

        $outcome = 'mismatch';
        if ($ai === null) {
            $outcome = 'ai_unreadable';
        } elseif ($ai === $final) {
            $outcome = 'match';
        }

        IpcAiFieldFeedback::updateOrCreate(
            ['ipc_batch_id' => $batch->id, 'field_key' => $key],
            [
                'ipc_log_id' => $log->id,
                'field_type' => $type,
                'ai_value' => $ai,
                'ai_trusted' => $trusted,
                'final_value' => $final,
                'source' => $source,
                'outcome' => $outcome,
                'packaging_type' => $batch->primary_packaging_type,
            ],
        );

        return true;
    }

    private function labelDateToIso(?string $value): ?string
    {
        return $value !== null && preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', trim($value), $m) === 1 && checkdate((int) $m[2], (int) $m[1], (int) $m[3])
            ? "{$m[3]}-{$m[2]}-{$m[1]}"
            : null;
    }

    private function normBatch(?string $value): ?string
    {
        $v = (string) preg_replace('/[\s\/]+/', '/', strtoupper(trim((string) $value)));

        return $v === '' ? null : $v;
    }

    private function batchLetters(?string $noBatch): ?string
    {
        if ($noBatch !== null && preg_match('#/([A-Z]{3})\s*$#i', $noBatch, $m) === 1) {
            return strtoupper($m[1]);
        }

        return null;
    }
}
