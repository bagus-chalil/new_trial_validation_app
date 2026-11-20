<?php

namespace App\Services\Vision;

use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;

/**
 * Turns a raw `label_bulk` Vision read into what the Batch Baru form can use: a normalised No. Batch,
 * the Mixing Date, the Kode Bulk and the FG product that Kode Bulk belongs to. Pure read/lookup, never a
 * decision and never persisted here. A value is `trusted` only when the Vision Service itself trusts it
 * (legible + format ok + every view agrees), the same bar StartupVisionEvaluator uses; the form only
 * fills a field automatically from a trusted value and offers an untrusted one as a suggestion.
 */
class LabelBulkScan
{
    private const BATCH_FORMAT = '/^\d{2}[A-Z]\d{3}\/[A-Z]{3}$/';

    /**
     * @param  array<string, mixed>  $raw  VisionResult::$raw of a label_bulk read
     * @return array<string, mixed>
     */
    public function interpret(VisionResult $result): array
    {
        $raw = $result->raw;
        $failed = $result->status === VisionResult::STATUS_ERROR;

        $noBatchRaw = $failed ? null : $this->clean($raw['no_batch_raw'] ?? null);
        $noBatch = $this->normaliseBatch($noBatchRaw);
        $mixingRaw = $failed ? null : $this->clean($raw['mixing_date_raw'] ?? null);
        $mixingIso = $this->labelDateToIso($mixingRaw);
        $kode = $failed ? null : $this->clean($raw['kode_bulk'] ?? null);
        $kode = $kode !== null && preg_match('/^\d{10}$/', $kode) === 1 ? $kode : null;

        $noBatchTrusted = $noBatch !== null && $this->trusted($raw, 'no_batch');
        $kodeTrusted = $kode !== null && $this->trusted($raw, 'kode_bulk');

        return [
            'vision_status' => $result->status,
            'error' => $failed ? $result->errorReason : null,
            'no_batch' => $noBatch,
            'no_batch_raw' => $noBatchRaw,
            'no_batch_trusted' => $noBatchTrusted,
            'mixing_date' => $mixingIso,
            'mixing_date_raw' => $mixingRaw,
            'mixing_date_trusted' => $mixingIso !== null && $this->trusted($raw, 'mixing_date'),
            'kode_bulk' => $kode,
            'kode_bulk_trusted' => $kodeTrusted,
            'product' => $kode !== null ? $this->productByBulkCode($kode) : null,
        ];
    }

    /** The active FG product that owns this bulk code, shaped like the FG picker's ProductOption. */
    public function productByBulkCode(string $bulkCode): ?array
    {
        $row = MasterProductBulkCode::query()
            ->where('bulk_code', $bulkCode)
            ->where('is_active', true)
            ->whereHas('masterProduct', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $product = MasterProduct::query()
            ->with(['bulkCodes' => fn ($q) => $q->where('is_active', true)->orderBy('bulk_code')->select(['id', 'master_product_id', 'bulk_code'])])
            ->find($row->master_product_id, ['id', 'fg_code', 'product_name', 'shelf_life_months']);

        return $product?->toArray();
    }

    /** "26X228/CKB", "26X228 CKB" or "CKB 26X228" -> "26X228/CKB"; anything else -> null (never guessed). */
    private function normaliseBatch(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $parts = preg_split('#[/\s]+#', strtoupper(trim($value))) ?: [];
        if (count($parts) !== 2) {
            return null;
        }

        foreach ([$parts, array_reverse($parts)] as [$code, $letters]) {
            $candidate = $code.'/'.$letters;
            if (preg_match(self::BATCH_FORMAT, $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    private function labelDateToIso(?string $label): ?string
    {
        if ($label === null || ! preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $label, $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }

        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    private function trusted(array $raw, string $key): bool
    {
        return (bool) ($raw['legible'][$key] ?? false)
            && (bool) ($raw['format_ok'][$key] ?? false)
            && (bool) ($raw['view_agreement'][$key] ?? false);
    }

    private function clean(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
