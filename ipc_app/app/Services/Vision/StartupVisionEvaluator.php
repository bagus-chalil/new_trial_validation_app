<?php

namespace App\Services\Vision;

use App\Models\IpcBatch;
use Closure;
use InvalidArgumentException;

/**
 * PASS / FAIL / REVIEW for the Startup AI cross-check (Label Bulk, IM, Primary Coding).
 * Same rules as VisionEvaluator, which this does not replace (that one is the tube-date path):
 *  - the Vision Service only reads; every decision is made here;
 *  - a value is compared only when the Vision Service trusts it (legible + format ok + every
 *    view agrees). Untrusted / unreadable / not comparable = REVIEW, never PASS or FAIL;
 *  - FAIL only when a trusted read disagrees with the expected value;
 *  - tube (packaging_type = tube, or unknown) is ALWAYS REVIEW — tube reads are not mature
 *    (the sensor mark prints over one EXP position), so they are never auto-PASS and a
 *    one-position conflict is never FAIL.
 * "Expected" comes from what the operator typed on the Startup Check form at scan time
 * (mixing/EXP are not saved yet) plus batch / master-product data.
 */
class StartupVisionEvaluator
{
    private const MATCH = 'match';

    private const MISMATCH = 'mismatch';

    private const REVIEW = 'review';

    private const INFO = 'info';

    public function __construct(private readonly ExpDateCalculator $calculator) {}

    /**
     * @param  array{mixing_date?: ?string, exp_date?: ?string}  $entered  Y-m-d strings from the form
     * @param  ?VisionResult  $tube  optional extra tube read (display only)
     */
    public function evaluate(IpcBatch $batch, string $fieldType, VisionResult $result, array $entered, ?VisionResult $tube = null): StartupEvaluation
    {
        if ($result->status === VisionResult::STATUS_ERROR) {
            return new StartupEvaluation(
                VisionEvaluation::REVIEW,
                'Vision Service gagal memproses foto ('.($result->errorReason ?? 'unknown').') - foto ulang atau isi manual.',
                [],
            );
        }

        $raw = $result->raw;
        $batch->loadMissing(['masterProduct', 'bulkCodes']);

        [$items, $forceReview, $note] = match ($fieldType) {
            'label_bulk' => $this->labelBulk($batch, $raw, $entered),
            'im_document' => $this->imDocument($batch, $raw, $entered),
            'coding_print' => $this->codingPrint($batch, $raw, $entered, $tube),
            default => [[], true, "Belum ada aturan pembanding untuk field_type '{$fieldType}'."],
        };

        $decision = $this->decide($items, $forceReview);
        $reason = match ($decision) {
            VisionEvaluation::PASS => 'Semua nilai yang terbaca sesuai.',
            VisionEvaluation::FAIL => 'Ada nilai yang terbaca tidak sesuai dengan yang diisi / master data.',
            default => $note ?? 'Ada nilai yang belum bisa dipastikan - cek manual.',
        };

        return new StartupEvaluation(
            $decision,
            $reason,
            $items,
            readSummary: $this->summary($items, 'read'),
            expectedSummary: $this->summary($items, 'expected'),
        );
    }

    /** @param list<array<string, mixed>> $items */
    private function decide(array $items, bool $forceReview): string
    {
        $verdicts = array_column($items, 'verdict');

        if ($forceReview) {
            return VisionEvaluation::REVIEW;
        }
        if (in_array(self::MISMATCH, $verdicts, true)) {
            return VisionEvaluation::FAIL;
        }
        if (in_array(self::REVIEW, $verdicts, true) || ! in_array(self::MATCH, $verdicts, true)) {
            return VisionEvaluation::REVIEW;
        }

        return VisionEvaluation::PASS;
    }

    /** @return array{0: list<array<string, mixed>>, 1: bool, 2: ?string} */
    private function labelBulk(IpcBatch $batch, array $raw, array $entered): array
    {
        $items = [];

        // No. Batch on the label vs the FG batch number (only the "/" vs space separator is
        // normalised, on the comparison side — the read value itself is shown as-is).
        $read = $raw['no_batch_raw'] ?? null;
        $items[] = $this->compare(
            'no_batch', 'No. Batch', $batch->no_batch, $read,
            $this->trusted($raw, 'no_batch'),
            fn () => $this->normBatch($read) === $this->normBatch($batch->no_batch),
        );

        // Mixing date on the label (DD/MM/YYYY) vs the form's mixing date.
        $read = $raw['mixing_date_raw'] ?? null;
        $expected = $entered['mixing_date'] ?? null;
        $readIso = $this->labelDateToIso($read);
        $items[] = $this->compare(
            'mixing_date', 'Mixing Date', $expected ? $this->isoToLabel($expected) : null, $read,
            $this->trusted($raw, 'mixing_date') && $readIso !== null,
            fn () => $readIso === $expected,
            $expected ? null : 'Isi Mixing Date di form dulu untuk dibandingkan.',
        );

        // Bulk code on the label vs any bulk code the batch was created with.
        $read = $raw['kode_bulk'] ?? null;
        $codes = $batch->bulkCodes->pluck('bulk_code')->push($batch->bulk_code)->filter()->unique()->values();
        $items[] = $this->compare(
            'kode_bulk', 'Kode Bulk', $codes->implode(', ') ?: null, $read,
            $this->trusted($raw, 'kode_bulk'),
            fn () => $codes->contains(strtoupper(trim((string) $read))),
        );

        return [$items, false, null];
    }

    /** @return array{0: list<array<string, mixed>>, 1: bool, 2: ?string} */
    private function imDocument(IpcBatch $batch, array $raw, array $entered): array
    {
        $items = [];

        // IM number: no reference value exists in IPC data, so it is shown, not compared.
        $items[] = $this->item('im_number', 'IM Number', null, $raw['im_number'] ?? null, self::INFO, null);

        $value = $raw['shelf_life_value'] ?? null;
        $trusted = $value !== null && $this->trusted($raw, 'shelf_life');
        $readMonths = $trusted ? ((int) ($value['years'] ?? 0) * 12) + (int) ($value['months'] ?? 0) : null;
        $readText = $value !== null ? ($value['years'] ?? 0).' th '.($value['months'] ?? 0).' bln' : null;

        $dbMonths = $batch->masterProduct?->shelf_life_months;
        $items[] = $this->compare(
            'shelf_life', 'Umur Simpan', $dbMonths ? $dbMonths.' bln (Master Produk)' : null, $readText,
            $trusted && $readMonths > 0,
            fn () => $readMonths === (int) $dbMonths,
            $dbMonths ? ($trusted ? null : 'Umur simpan tidak pasti di foto.') : 'Shelf-life produk belum diisi di Master Produk.',
        );

        // The EXP the operator typed must be mixing + the shelf-life the IM states.
        $mixing = $entered['mixing_date'] ?? null;
        $exp = $entered['exp_date'] ?? null;
        if ($trusted && $readMonths > 0 && $mixing && $exp) {
            $expDmy = date('dmy', strtotime($exp));
            try {
                $computed = $this->calculator->fromMfd(date('dmy', strtotime($mixing)), $readMonths);
                $items[] = $this->compare(
                    'exp_formula', 'EXP = Mixing + Umur Simpan', $expDmy, $computed,
                    true,
                    fn () => $computed === $expDmy,
                );
            } catch (InvalidArgumentException $e) {
                $items[] = $this->item('exp_formula', 'EXP = Mixing + Umur Simpan', $expDmy, null, self::REVIEW, $e->getMessage());
            }
        } elseif ($trusted) {
            $items[] = $this->item('exp_formula', 'EXP = Mixing + Umur Simpan', null, null, self::REVIEW, 'Isi Mixing Date dan EXP Date di form dulu.');
        }

        return [$items, false, null];
    }

    /** @return array{0: list<array<string, mixed>>, 1: bool, 2: ?string} */
    private function codingPrint(IpcBatch $batch, array $raw, array $entered, ?VisionResult $tube): array
    {
        $items = [];
        $kind = $raw['packaging_type'] ?? 'unknown';

        // Batch = the 3 letters after "/" of the FG batch number (e.g. 26X005/EKB -> EKB).
        $expectedBatch = $this->batchLetters($batch->no_batch);
        $read = $raw['batch_raw'] ?? null;
        $items[] = $this->compare(
            'coding_batch', 'Batch (coding)', $expectedBatch, $read,
            $this->trusted($raw, 'batch', 'trusted'),
            fn () => strtoupper(trim((string) $read)) === $expectedBatch,
            $expectedBatch ? null : 'Batch 3 huruf tidak bisa ditentukan dari No. Batch.',
        );

        // EXP vs the form's EXP date. MM_YYYY prints no day, so only month + year compare.
        $exp = $entered['exp_date'] ?? null;
        $format = $raw['exp_format'] ?? null;
        $parts = $raw['exp_parts'] ?? null;
        $trustedExp = $this->trusted($raw, 'exp', 'trusted');
        if ($format === 'MM_YYYY' && is_array($parts)) {
            $items[] = $this->compare(
                'coding_exp', 'EXP (coding, bulan/tahun)', $exp ? date('m/Y', strtotime($exp)) : null, ($parts['month'] ?? '').'/'.($parts['year'] ?? ''),
                $trustedExp,
                fn () => (int) ($parts['month'] ?? 0) === (int) date('n', strtotime($exp)) && (int) ($parts['year'] ?? 0) === (int) date('Y', strtotime($exp)),
                $exp ? 'EXP tercetak bulan-tahun; hari tidak dibandingkan.' : 'Isi EXP Date di form dulu.',
            );
        } elseif ($format === 'DDMMYY' && is_array($parts)) {
            $readText = ($parts['day'] ?? '').($parts['month'] ?? '').($parts['year'] ?? '');
            $items[] = $this->compare(
                'coding_exp', 'EXP (coding)', $exp ? date('dmy', strtotime($exp)) : null, $readText,
                $trustedExp,
                fn () => $readText === date('dmy', strtotime($exp)),
                $exp ? null : 'Isi EXP Date di form dulu.',
            );
        } else {
            $items[] = $this->item('coding_exp', 'EXP (coding)', $exp ? date('dmy', strtotime($exp)) : null, $raw['exp_raw'] ?? null, self::REVIEW, 'Format EXP tidak dikenali / tidak terbaca.');
        }

        // Tube: display the extra tube read, but the whole result is forced to REVIEW.
        if ($tube !== null && $tube->status !== VisionResult::STATUS_ERROR) {
            $items[] = $this->item('tube_exp', 'EXP (jalur tube, info)', $exp ? date('dmy', strtotime($exp)) : null, $tube->value, self::INFO, 'Jalur tube belum matang - tidak dipakai untuk keputusan.');
        }

        if ($kind === 'tube') {
            return [$items, true, 'Kemasan tube: hasil baca AI belum dipakai untuk keputusan - cek manual.'];
        }
        if ($kind !== 'non_tube') {
            return [$items, true, 'Jenis kemasan tidak pasti (tube / non-tube) - cek manual.'];
        }

        return [$items, false, null];
    }

    /** One comparison row. $check is only called when the read is trusted and an expected value exists. */
    private function compare(string $key, string $label, ?string $expected, ?string $read, bool $trusted, Closure $check, ?string $note = null): array
    {
        if ($read === null || $read === '' || $read === '/') {
            return $this->item($key, $label, $expected, null, self::REVIEW, $note ?? 'Tidak terbaca di foto.');
        }
        if ($expected === null || $expected === '') {
            return $this->item($key, $label, null, $read, self::REVIEW, $note ?? 'Tidak ada nilai pembanding.');
        }
        if (! $trusted) {
            return $this->item($key, $label, $expected, $read, self::REVIEW, 'Bacaan belum yakin (view tidak sepakat / format tidak sesuai) - cek manual.');
        }

        return $this->item($key, $label, $expected, $read, $check() ? self::MATCH : self::MISMATCH, null);
    }

    private function item(string $key, string $label, ?string $expected, ?string $read, string $verdict, ?string $note): array
    {
        return compact('key', 'label', 'expected', 'read', 'verdict', 'note');
    }

    /** Trusted only when legible + format ok + every view agrees (Vision Service flags). */
    private function trusted(array $raw, string $key, ?string $trustedMapKey = null): bool
    {
        if ($trustedMapKey !== null && isset($raw[$trustedMapKey][$key])) {
            return (bool) $raw[$trustedMapKey][$key];
        }

        return (bool) ($raw['legible'][$key] ?? false)
            && (bool) ($raw['format_ok'][$key] ?? false)
            && (bool) ($raw['view_agreement'][$key] ?? false);
    }

    private function normBatch(?string $value): string
    {
        return (string) preg_replace('/[\s\/]+/', '/', strtoupper(trim((string) $value)));
    }

    private function batchLetters(?string $noBatch): ?string
    {
        return preg_match('/\/([A-Z]{3})$/', $this->normBatch($noBatch), $m) ? $m[1] : null;
    }

    private function labelDateToIso(?string $label): ?string
    {
        if (! $label || ! preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $label, $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }

        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    private function isoToLabel(string $iso): string
    {
        return date('d/m/Y', strtotime($iso));
    }

    /** @param list<array<string, mixed>> $items */
    private function summary(array $items, string $field): ?string
    {
        $text = collect($items)
            ->filter(fn ($i) => $i[$field] !== null && $i[$field] !== '')
            ->map(fn ($i) => $i['key'].'='.$i[$field])
            ->implode('; ');

        return $text === '' ? null : mb_substr($text, 0, 250);
    }
}
