<?php

namespace App\Services\Vision;

use App\Models\IpcBatch;
use Closure;

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
     * @param  array{mixing_date?: ?string, exp_date?: ?string, shelf_life_months?: ?int}  $entered  form values (dates as Y-m-d)
     * @param  ?VisionResult  $tube  optional extra tube read (display only)
     * @param  ?VisionResult  $tubeLlm  optional tube_exp_llm read (display only, never decides)
     */
    public function evaluate(IpcBatch $batch, string $fieldType, VisionResult $result, array $entered, ?VisionResult $tube = null, ?VisionResult $tubeLlm = null): StartupEvaluation
    {
        if ($result->status === VisionResult::STATUS_ERROR) {
            return new StartupEvaluation(
                VisionEvaluation::REVIEW,
                'Vision Service gagal memproses foto ('.($result->errorReason ?? 'unknown').') - foto ulang atau isi manual.',
                [],
            );
        }

        $raw = $result->raw;

        // The photo never reached the AI because it is blank / almost black (see PhotoQuality).
        if (($raw['photo_quality']['blank'] ?? false) === true) {
            return new StartupEvaluation(
                VisionEvaluation::REVIEW,
                'Foto kosong atau terlalu gelap - ambil ulang foto ini.',
                [$this->item(
                    'photo_quality', 'Kualitas foto', 'Foto terlihat jelas', 'Kosong / terlalu gelap', self::INFO,
                    'Foto ini kosong atau hampir hitam (kamera gagal menangkap gambar). Ambil ulang: pastikan lensa tidak tertutup, cahaya cukup, dan tulisan terlihat di layar sebelum menekan Ambil Foto.',
                    retake: true,
                )],
            );
        }

        $batch->loadMissing(['masterProduct', 'bulkCodes']);

        [$items, $forceReview, $note] = match ($fieldType) {
            'label_bulk' => $this->labelBulk($batch, $raw, $entered),
            'im_document' => $this->imDocument($batch, $raw, $entered),
            'coding_print' => $this->codingPrint($batch, $raw, $entered, $tube, $tubeLlm),
            'coding_formula' => $this->codingFormula($batch, $raw, $entered, $tube, $tubeLlm),
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

    /**
     * "Pakai saran": values the AI read that the operator may copy into a still-EMPTY form field.
     * Only non-decision inputs (Mixing Date from Label Bulk, Shelf Life from the IM) and only when
     * the Vision Service trusts the read. Never overwrites something already typed, never touches a
     * Conform / Not Conform toggle or a decision; the operator must click to apply.
     *
     * @param  array{mixing_date?: ?string, shelf_life_months?: ?int}  $entered
     * @return list<array{field: string, label: string, value: string|int, display: string}>
     */
    public function suggestions(string $fieldType, VisionResult $result, array $entered): array
    {
        if ($result->status === VisionResult::STATUS_ERROR) {
            return [];
        }
        $raw = $result->raw;
        $out = [];

        if ($fieldType === 'label_bulk' && empty($entered['mixing_date'])) {
            $read = $raw['mixing_date_raw'] ?? null;
            $iso = $this->labelDateToIso($read);
            if ($iso !== null && $this->trusted($raw, 'mixing_date')) {
                $out[] = ['field' => 'mixing_date', 'label' => 'Mixing Date', 'value' => $iso, 'display' => (string) $read];
            }
        }

        if ($fieldType === 'im_document' && empty($entered['shelf_life_months'])) {
            $value = $raw['shelf_life_value'] ?? null;
            if ($value !== null && $this->trusted($raw, 'shelf_life')) {
                $months = ((int) ($value['years'] ?? 0) * 12) + (int) ($value['months'] ?? 0);
                if ($months > 0) {
                    $out[] = ['field' => 'shelf_life_months', 'label' => 'Shelf Life', 'value' => $months, 'display' => $months.' bulan'];
                }
            }
        }

        return $out;
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
            untrustedNote: $this->retakeNote($raw, 'no_batch'),
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
            $this->retakeNote($raw, 'mixing_date'),
        );

        // Bulk code on the label vs any bulk code the batch was created with.
        $read = $raw['kode_bulk'] ?? null;
        $codes = $batch->bulkCodes->pluck('bulk_code')->push($batch->bulk_code)->filter()->unique()->values();
        $items[] = $this->compare(
            'kode_bulk', 'Kode Bulk', $codes->implode(', ') ?: null, $read,
            $this->trusted($raw, 'kode_bulk'),
            fn () => $codes->contains(strtoupper(trim((string) $read))),
            untrustedNote: $this->retakeNote($raw, 'kode_bulk'),
        );

        $items[] = $this->labelBulkFormat($raw);

        return [$items, false, null];
    }

    /**
     * Is this photo really a readable Label Bulk (yellow label with Kode Bulk, No Batch, Mixing Date)?
     * Advice only: always INFO (it never changes PASS/REVIEW/FAIL), `retake` = true shows the amber advice banner. Format rules follow real
     * data, not an ideal: No. Batch is written "26X035/KKB" (82%), "26X035 KKB" or "KKB 26X035", so
     * separator and order are tolerated; Kode Bulk is 10 digits (697 of 698 batches).
     *
     * @return array<string, mixed>
     */
    private function labelBulkFormat(array $raw): array
    {
        $problems = [];

        $noBatch = $raw['no_batch_raw'] ?? null;
        if ($noBatch === null || trim((string) $noBatch) === '') {
            $problems[] = 'No. Batch tidak terbaca';
        } elseif (! $this->batchNumberFormatOk((string) $noBatch)) {
            $problems[] = "No. Batch '{$noBatch}' tidak sesuai bentuk biasa (contoh 26X035/KKB)";
        }

        $mixing = $raw['mixing_date_raw'] ?? null;
        if ($mixing === null || $this->labelDateToIso($mixing) === null) {
            $problems[] = $mixing === null ? 'Mixing Date tidak terbaca' : "Mixing Date '{$mixing}' bukan tanggal DD/MM/YYYY yang valid";
        }

        $kode = $raw['kode_bulk'] ?? null;
        if ($kode === null || preg_match('/^\d{10}$/', trim((string) $kode)) !== 1) {
            $problems[] = $kode === null ? 'Kode Bulk tidak terbaca' : "Kode Bulk '{$kode}' bukan 10 digit";
        }

        if ($problems === []) {
            return $this->item('photo_format', 'Format foto Label Bulk', 'Label Bulk lengkap', 'Sesuai format', self::INFO, null);
        }

        return $this->item(
            'photo_format', 'Format foto Label Bulk', 'Label Bulk lengkap', 'Belum sesuai', self::INFO,
            'Foto belum sesuai format Label Bulk: '.implode('; ', $problems).'. Foto seluruh label kuning (Kode Bulk, No Batch, Mixing Date harus terlihat utuh), tegak lurus, tanpa pantulan cahaya.',
            retake: true,
        );
    }

    /** "26X035/KKB", "26X035 KKB", "KKB 26X035" or a bare "26X035" (all seen in real data). */
    private function batchNumberFormatOk(string $value): bool
    {
        $parts = preg_split('#[/\s]+#', strtoupper(trim($value))) ?: [];
        $isCode = fn (string $p): bool => preg_match('/^\d{2}[A-Z]\d{3}$/', $p) === 1;
        $isLetters = fn (string $p): bool => preg_match('/^[A-Z]{3,4}$/', $p) === 1;

        return match (count($parts)) {
            1 => $isCode($parts[0]),
            2 => ($isCode($parts[0]) && $isLetters($parts[1])) || ($isLetters($parts[0]) && $isCode($parts[1])),
            default => false,
        };
    }

    /**
     * Is this photo the IM page that states the shelf-life ("n Tahun dari Tanggal Mixing") with its
     * IM number (IM-XXX-NAMA PRODUK, version suffix optional)? Advice only, INFO + retake, never changes the decision. A photo of
     * the wrong part of the IM, or one that is cut off, shows up as an unstable shelf-life / IM number
     * across the views the Vision Service reads.
     *
     * @return array<string, mixed>
     */
    private function imFormat(array $raw): array
    {
        $problems = [];

        $imNumber = $raw['im_number'] ?? null;
        if ($imNumber === null || trim((string) $imNumber) === '') {
            $problems[] = 'No. IM (IM-XXX-NAMA PRODUK) tidak terbaca';
        } elseif (preg_match('/^IM[-\s]*[A-Z]{3}/i', trim((string) $imNumber)) !== 1) {
            $problems[] = "No. IM '{$imNumber}' tidak berawalan IM-XXX";
        } elseif (isset($raw['view_agreement']['im_number']) && ! $raw['view_agreement']['im_number']) {
            $problems[] = 'No. IM terbaca berbeda-beda antar sudut (bagian tepi mungkin terpotong)';
        }

        $value = $raw['shelf_life_value'] ?? null;
        if ($value === null) {
            $problems[] = isset($raw['view_agreement']['shelf_life']) && ! $raw['view_agreement']['shelf_life']
                ? "pernyataan umur simpan ('n Tahun dari Tanggal Mixing') terbaca tidak konsisten antar sudut"
                : "pernyataan umur simpan ('n Tahun dari Tanggal Mixing') tidak ditemukan di foto";
        } else {
            $months = ((int) ($value['years'] ?? 0) * 12) + (int) ($value['months'] ?? 0);
            if ($months < 6 || $months > 120) {
                $problems[] = "umur simpan terbaca {$months} bulan, di luar kewajaran (6-120 bulan)";
            }
        }

        if ($problems === []) {
            return $this->item('photo_format', 'Format foto IM', 'IM dengan umur simpan', 'Sesuai format', self::INFO, null);
        }

        return $this->item(
            'photo_format', 'Format foto IM', 'IM dengan umur simpan', 'Belum sesuai', self::INFO,
            'Foto IM belum sesuai format: '.implode('; ', $problems).'. Foto bagian IM yang memuat No. IM dan teks umur simpan (mis. di bagian A.2 Apperance Packing, "EXP Date ... 2 Tahun dari Tanggal Mixing") secara utuh, tidak terpotong, tegak lurus.',
            retake: true,
        );
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

        // What the operator filled (form > batch snapshot > Master Produk) is what EXP is based on.
        $dbMonths = $entered['shelf_life_months'] ?? $batch->shelf_life_months ?? $batch->masterProduct?->shelf_life_months;
        $items[] = $this->compare(
            'shelf_life', 'Umur Simpan', $dbMonths ? $dbMonths.' bln (isian / Master Produk)' : null, $readText,
            $trusted && $readMonths > 0,
            fn () => $readMonths === (int) $dbMonths,
            $dbMonths ? ($trusted ? null : 'Umur simpan tidak pasti di foto.') : 'Shelf-life belum diisi di form.',
            $this->retakeNote($raw, 'shelf_life'),
        );

        // The EXP the operator typed must be mixing + the shelf-life the IM states.
        $mixing = $entered['mixing_date'] ?? null;
        $exp = $entered['exp_date'] ?? null;
        if ($trusted && $readMonths > 0 && $mixing && $exp) {
            $expYmd = date('Y-m-d', strtotime($exp));
            $computed = $this->calculator->expDateFromMixing(date('Y-m-d', strtotime($mixing)), $readMonths);
            $items[] = $this->compare(
                'exp_formula', 'EXP = Mixing + Umur Simpan', date('dmy', strtotime($exp)), $computed ? date('dmy', strtotime($computed)) : null,
                true,
                fn () => $computed === $expYmd,
            );
        } elseif ($trusted) {
            $items[] = $this->item('exp_formula', 'EXP = Mixing + Umur Simpan', null, null, self::REVIEW, 'Isi Mixing Date dan EXP Date di form dulu.');
        }

        // IM layouts differ per product, so there is no fixed IM standard to validate against
        // (user decision 2026-11-19): no "Format foto IM" check, and no "ambil ulang" banner for IM.
        // Values are still read and compared, but a doubtful read only needs a manual check.
        $items = array_map(fn (array $item) => [...$item, 'retake' => false], $items);

        return [$items, false, null];
    }

    /** @return array{0: list<array<string, mixed>>, 1: bool, 2: ?string} */
    private function codingPrint(IpcBatch $batch, array $raw, array $entered, ?VisionResult $tube, ?VisionResult $tubeLlm = null): array
    {
        $items = [];
        // The packaging type chosen on the form decides the path; the AI's own guess only cross-checks it.
        $chosen = $entered['packaging_type'] ?? null;
        $aiKind = $raw['packaging_type'] ?? 'unknown';
        $kind = $chosen ?: $aiKind;
        $kindMismatch = $chosen && $aiKind !== 'unknown' && $aiKind !== $chosen;
        if ($chosen) {
            $items[] = $this->item('packaging', 'Jenis kemasan', $chosen === 'tube' ? 'Tube' : 'Non-tube', $aiKind === 'unknown' ? null : ($aiKind === 'tube' ? 'Tube' : 'Non-tube'), $kindMismatch ? self::REVIEW : self::INFO, $kindMismatch ? 'AI membaca jenis kemasan yang berbeda dari pilihan - cek manual.' : null);
        }

        // Batch = the 3 letters after "/" of the FG batch number (e.g. 26X005/EKB -> EKB).
        $expectedBatch = $this->batchLetters($batch->no_batch);
        $read = $raw['batch_raw'] ?? null;
        $items[] = $this->compare(
            'coding_batch', 'Batch (coding)', $expectedBatch, $read,
            $this->trusted($raw, 'batch', 'trusted'),
            fn () => strtoupper(trim((string) $read)) === $expectedBatch,
            $expectedBatch ? null : 'Batch 3 huruf tidak bisa ditentukan dari No. Batch.',
            $this->retakeNote($raw, 'batch', 'trusted'),
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
                $this->retakeNote($raw, 'exp', 'trusted'),
            );
        } elseif ($format === 'DDMMYY' && is_array($parts)) {
            $readText = ($parts['day'] ?? '').($parts['month'] ?? '').($parts['year'] ?? '');
            $items[] = $this->compare(
                'coding_exp', 'EXP (coding)', $exp ? date('dmy', strtotime($exp)) : null, $readText,
                $trustedExp,
                fn () => $readText === date('dmy', strtotime($exp)),
                $exp ? null : 'Isi EXP Date di form dulu.',
                $this->retakeNote($raw, 'exp', 'trusted'),
            );
        } else {
            $items[] = $this->item('coding_exp', 'EXP (coding)', $exp ? date('dmy', strtotime($exp)) : null, $raw['exp_raw'] ?? null, self::REVIEW, 'Format EXP tidak dikenali / tidak terbaca - ambil ulang foto coding dengan seluruh tulisan terlihat.', retake: true);
        }

        // Tube: display the extra tube read, but the whole result is forced to REVIEW.
        if ($tube !== null && $tube->status !== VisionResult::STATUS_ERROR) {
            $items[] = $this->item('tube_exp', 'EXP (jalur tube, info)', $exp ? date('dmy', strtotime($exp)) : null, $tube->value, self::INFO, 'Jalur tube belum matang - tidak dipakai untuk keputusan.');
        }

        if ($tubeLlm !== null) {
            $items[] = $this->tubeLlmItem($tubeLlm, $exp);
        }

        if ($kind === 'tube') {
            return [$items, true, 'Kemasan tube: hasil baca AI belum dipakai untuk keputusan - cek manual.'];
        }
        if ($kindMismatch) {
            return [$items, true, 'Jenis kemasan yang dipilih berbeda dari bacaan AI - cek manual.'];
        }
        if ($kind !== 'non_tube') {
            return [$items, true, 'Jenis kemasan tidak pasti (tube / non-tube) - cek manual.'];
        }

        return [$items, false, null];
    }

    /**
     * Primary Coding read by coding formula (1 or 2 photos merged by the Vision Service). Per field:
     * only status "OK" is trusted; CONFLICT / LOW_CONFIDENCE / NOT_FOUND / missing = REVIEW, never
     * PASS or FAIL. EXP is compared through date + granularity (day = full date, month = month/year),
     * never by parsing the printed value. Tube and non-tube follow the same rules, except that a tube
     * result is still forced to REVIEW while vision.startup_ai.tube_force_review is on (old policy).
     *
     * @return array{0: list<array<string, mixed>>, 1: bool, 2: ?string}
     */
    private function codingFormula(IpcBatch $batch, array $raw, array $entered, ?VisionResult $tube, ?VisionResult $tubeLlm): array
    {
        $items = [];
        $chosen = $entered['packaging_type'] ?? null;
        if ($chosen) {
            $items[] = $this->item('packaging', 'Jenis kemasan', $chosen === IpcBatch::PACKAGING_TUBE ? 'Tube' : 'Non-tube', null, self::INFO, null);
        }

        // Batch: 3 letters after "/" of No. Batch. A batch printed with another length (e.g. 26Y070X) is
        // shown as info and left to a manual check, never forced to match.
        $expectedBatch = CodingFormulaRead::batchLetters($batch->no_batch);
        $batchField = CodingFormulaRead::field($raw, 'batch');
        $read = $batchField['value'] ?? null;
        $readBatch = strtoupper(trim((string) $read));
        if ($this->formulaUnusable($batchField)) {
            $items[] = $this->formulaFieldItem('coding_batch', 'Batch (coding)', $expectedBatch, $batchField);
        } elseif (CodingFormulaRead::isOk($batchField) && $read !== null && strlen($readBatch) !== 3) {
            $items[] = $this->item('coding_batch', 'Batch (coding)', $expectedBatch, $read, self::REVIEW,
                'Batch tercetak '.strlen($readBatch).' karakter, bukan 3 huruf - tidak dibandingkan otomatis, cek manual.'.$this->photoNote($batchField));
        } else {
            $item = $this->compare(
                'coding_batch', 'Batch (coding)', $expectedBatch, $read,
                CodingFormulaRead::isOk($batchField),
                fn () => $readBatch === $expectedBatch,
                $expectedBatch ? null : 'Batch 3 huruf tidak bisa ditentukan dari No. Batch.',
                $this->formulaUntrustedNote($batchField),
            );
            $items[] = $this->withPhotoNote($item, $batchField);
        }

        // EXP: the form's EXP (system-computed), else the batch's saved EXP, at the read's granularity.
        $expField = CodingFormulaRead::field($raw, 'exp');
        $granularity = $expField['granularity'] ?? null;
        $reference = ($entered['exp_date'] ?? null) ?: $batch->exp_date?->format('Y-m-d');
        $expectedKey = CodingFormulaRead::referenceKey($reference, $granularity);
        $readKey = CodingFormulaRead::expKey($expField);
        $label = $granularity === CodingFormulaRead::GRANULARITY_MONTH ? 'EXP (coding, bulan/tahun)' : 'EXP (coding)';
        if ($this->formulaUnusable($expField)) {
            $fallback = $reference ? date('d/m/Y', strtotime($reference)) : null;
            $items[] = $this->formulaFieldItem('coding_exp', 'EXP (coding)', $fallback, $expField);
        } elseif (CodingFormulaRead::isOk($expField) && $readKey === null) {
            $items[] = $this->item('coding_exp', $label, CodingFormulaRead::displayKey($expectedKey), $expField['value'] ?? null, self::REVIEW,
                'Tanggal EXP tidak bisa dipastikan dari bacaan AI - cek manual.'.$this->photoNote($expField));
        } else {
            $note = $reference ? null : 'Isi EXP Date di form dulu.';
            if ($granularity === CodingFormulaRead::GRANULARITY_MONTH && $reference) {
                $note = 'EXP tercetak bulan-tahun; hari tidak dibandingkan.';
            }
            $item = $this->compare(
                'coding_exp', $label, CodingFormulaRead::displayKey($expectedKey), $expField['value'] ?? null,
                CodingFormulaRead::isOk($expField),
                fn () => $readKey === $expectedKey,
                $note,
                $this->formulaUntrustedNote($expField),
            );
            if (in_array($item['verdict'], [self::MATCH, self::MISMATCH], true) && $note !== null) {
                $item['note'] = $note;
            }
            $items[] = $this->withPhotoNote($item, $expField);
        }

        // Extra fields of the formula (MFG, init, ...): no reference in IPC data, shown only. A REQUIRED
        // one that is not OK still means REVIEW.
        $required = is_array($raw['required_fields'] ?? null) ? $raw['required_fields'] : ['batch', 'exp'];
        foreach ($raw['fields'] ?? [] as $key => $field) {
            if (in_array($key, ['batch', 'exp'], true) || ! is_array($field)) {
                continue;
            }
            $isRequired = in_array($key, $required, true) || ($field['required'] ?? false) === true;
            if (! $isRequired && ($field['value'] ?? null) === null) {
                continue; // an optional field that was not read is just noise on the card
            }
            $ok = CodingFormulaRead::isOk($field);
            $items[] = $this->item('coding_'.$key, strtoupper((string) $key).' (coding, info)', null, $field['value'] ?? null,
                $isRequired && ! $ok ? self::REVIEW : self::INFO,
                $isRequired && ! $ok ? $this->formulaStatusNote($field) : $this->photoNote($field, leadingSpace: false));
        }
        foreach ($required as $key) {
            if (! in_array($key, ['batch', 'exp'], true) && ! isset($raw['fields'][$key])) {
                $items[] = $this->item('coding_'.$key, strtoupper((string) $key).' (coding)', null, null, self::REVIEW, 'Wajib menurut formula tapi tidak ada di hasil AI - cek manual.');
            }
        }

        $source = $raw['formula_source'] ?? null;
        $items[] = $source === 'auto'
            ? $this->item('formula', 'Formula coding', null, 'Otomatis', self::INFO, 'Formula otomatis (SKU belum punya formula di Master Produk).')
            : $this->item('formula', 'Formula coding', null, is_array($raw['formula'] ?? null) ? implode(' | ', $raw['formula']) : $source, self::INFO,
                $source === 'catalog' ? 'Formula dari katalog (diisi di Master Produk).' : 'Formula dari Master Produk.');

        // Old tube reads, only when vision.startup_ai.legacy_tube_reads is on: display only.
        if ($tube !== null && $tube->status !== VisionResult::STATUS_ERROR) {
            $items[] = $this->item('tube_exp', 'EXP (jalur tube lama, info)', $reference ? date('dmy', strtotime($reference)) : null, $tube->value, self::INFO, 'Jalur lama - tidak dipakai untuk keputusan.');
        }
        if ($tubeLlm !== null) {
            $items[] = $this->tubeLlmItem($tubeLlm, $reference);
        }

        if ($chosen === IpcBatch::PACKAGING_TUBE && config('vision.startup_ai.tube_force_review', true)) {
            return [$items, true, 'Kemasan tube: hasil baca AI belum dipakai untuk keputusan - cek manual.'];
        }
        if (! $chosen) {
            return [$items, true, 'Jenis Kemasan Primer belum dipilih - cek manual.'];
        }

        return [$items, false, null];
    }

    /** CONFLICT, or a field the service did not return at all. */
    private function formulaUnusable(?array $field): bool
    {
        return $field === null || ($field['status'] ?? null) === 'CONFLICT';
    }

    /** Row for a field that is missing or read differently on the two photos: always REVIEW. */
    private function formulaFieldItem(string $key, string $label, ?string $expected, ?array $field): array
    {
        if ($field === null) {
            return $this->item($key, $label, $expected, null, self::REVIEW, 'Tidak ada di hasil AI - cek manual.');
        }

        $perPhoto = collect($field['per_photo'] ?? [])
            ->map(fn ($p) => 'foto '.((int) ($p['photo'] ?? 0) + 1).': '.($p['value'] ?? '-'))
            ->implode(' / ');

        return $this->item($key, $label, $expected, $perPhoto !== '' ? $perPhoto : null, self::REVIEW,
            'Foto 1 dan foto 2 terbaca beda - cek manual.');
    }

    /** Why a read field is not trusted (LOW_CONFIDENCE / AMBIGUOUS / NOT_FOUND ...), operator words. */
    private function formulaUntrustedNote(?array $field): string
    {
        return $this->formulaStatusNote($field).' Ambil ulang foto lebih dekat, tegak lurus, tanpa pantulan bila perlu.';
    }

    private function formulaStatusNote(?array $field): string
    {
        return match ($field['status'] ?? null) {
            'AMBIGUOUS' => 'AI menemukan lebih dari satu kandidat - cek manual.',
            'NOT_FOUND' => 'Tidak ditemukan sesuai formula - cek manual.',
            'LOW_CONFIDENCE' => 'Bacaan AI belum yakin (sudut foto tidak sepakat) - cek manual.',
            null => 'Tidak ada di hasil AI - cek manual.',
            default => 'Status AI '.$field['status'].' - cek manual.',
        };
    }

    private function photoNote(?array $field, bool $leadingSpace = true): ?string
    {
        $text = CodingFormulaRead::photosText($field);
        if ($text === null) {
            return $leadingSpace ? '' : null;
        }

        return ($leadingSpace ? ' ' : '').'Terbaca di '.$text.'.';
    }

    /** @param array<string, mixed> $item */
    private function withPhotoNote(array $item, ?array $field): array
    {
        $photo = $this->photoNote($field, leadingSpace: false);
        if ($photo !== null && $item['read'] !== null) {
            $item['note'] = trim(($item['note'] ?? '').' '.$photo);
        }

        return $item;
    }

    /** One comparison row. $check is only called when the read is trusted and an expected value exists. */
    private function compare(string $key, string $label, ?string $expected, ?string $read, bool $trusted, Closure $check, ?string $note = null, ?string $untrustedNote = null): array
    {
        if ($read === null || $read === '' || $read === '/') {
            return $this->item($key, $label, $expected, null, self::REVIEW, 'Tidak terbaca di foto - ambil ulang: fokus, lebih dekat, cahaya cukup tanpa pantulan, seluruh tulisan masuk frame.', retake: true);
        }
        if ($expected === null || $expected === '') {
            return $this->item($key, $label, null, $read, self::REVIEW, $note ?? 'Tidak ada nilai pembanding.');
        }
        if (! $trusted) {
            return $this->item($key, $label, $expected, $read, self::REVIEW, $untrustedNote ?? 'Bacaan belum yakin (view tidak sepakat / format tidak sesuai) - cek manual.', retake: true);
        }

        return $this->item($key, $label, $expected, $read, $check() ? self::MATCH : self::MISMATCH, null);
    }

    /**
     * Why a value was not trusted, in words an operator can act on (photo advice), from the
     * flags the Vision Service already returns. Advice only: it never changes a decision.
     */
    private function retakeNote(array $raw, string $key, ?string $trustedMapKey = null): string
    {
        if ($trustedMapKey !== null && isset($raw[$trustedMapKey][$key])) {
            return 'Bacaan belum yakin - foto kurang jelas / ada bagian tertutup. Ambil ulang lebih dekat dan tegak lurus.';
        }
        if (isset($raw['legible'][$key]) && ! $raw['legible'][$key]) {
            return 'Bagian ini buram, terlalu gelap, atau terpotong di foto - ambil ulang: fokus, cahaya cukup, seluruh tulisan masuk frame.';
        }
        if (isset($raw['format_ok'][$key]) && ! $raw['format_ok'][$key]) {
            return 'Isi terbaca tapi formatnya tidak sesuai - bagian bisa terpotong/tertutup. Ambil ulang dengan seluruh tulisan terlihat.';
        }
        if (isset($raw['view_agreement'][$key]) && ! $raw['view_agreement'][$key]) {
            return 'Bacaan berubah-ubah antar sudut - foto mungkin terlalu gelap, silau (pantulan), atau miring. Ambil ulang dengan cahaya cukup, tanpa glare, tegak lurus.';
        }

        return 'Bacaan belum yakin - cek manual atau ambil ulang foto.';
    }

    /**
     * tube_exp_llm, ADVISORY. exp_raw is shown exactly as read (never stripped or corrected).
     * Always INFO: the gap to the operator's EXP is only noted, never a mismatch/FAIL, and an
     * OK is not strong evidence (the sensor mark prints over one crimp digit).
     * LOW_CONFIDENCE / ERROR mean review, not fail.
     *
     * @return array<string, mixed>
     */
    private function tubeLlmItem(VisionResult $r, ?string $exp): array
    {
        $expected = $exp ? date('dmy', strtotime($exp)) : null;
        $raw = $r->raw;
        $read = $raw['exp_raw'] ?? null;
        $n = (int) ($raw['trusted_sources'] ?? 0);
        $total = (int) ($raw['sources_total'] ?? 0);

        if ($r->status === VisionResult::STATUS_ERROR) {
            return $this->item('tube_exp_llm', 'EXP (tube, AI LLM)', $expected, null, self::INFO, 'AI LLM tidak tersedia ('.($r->errorReason ?? 'error').') - isi manual.');
        }

        $note = $r->status === VisionResult::STATUS_OK
            ? "AI membaca konsisten dari {$n}/{$total} sumber foto."
            : 'AI belum yakin (LOW_CONFIDENCE) - cek manual.';
        if ($read !== null && $expected !== null) {
            $note .= $read === $expected ? ' Sama dengan EXP di form.' : ' BEDA dari EXP di form - cek manual.';
        }
        $note .= ' Garis hitam bisa menutupi satu digit; bukan bukti kuat.';

        return $this->item('tube_exp_llm', 'EXP (tube, AI LLM)', $expected, $read, self::INFO, $note);
    }

    private function item(string $key, string $label, ?string $expected, ?string $read, string $verdict, ?string $note, bool $retake = false): array
    {
        return compact('key', 'label', 'expected', 'read', 'verdict', 'note', 'retake');
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
