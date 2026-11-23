<?php

namespace App\Services\Vision;

/**
 * Reading helpers for a Vision Service `coding_formula` response (shared by the evaluator, the read
 * log and the accuracy feedback, so all three read it the same way). Only READS the response:
 *  - a field is usable only when its own status is "OK" (CONFLICT / LOW_CONFIDENCE / NOT_FOUND /
 *    AMBIGUOUS / missing are never trusted);
 *  - EXP is compared through `date` + `granularity` (day = full date, month = month + year), never by
 *    parsing the printed `value` again (NOV 29, 112028, 27.10.29 are already parsed by the service);
 *  - `value` is always kept exactly as read.
 */
final class CodingFormulaRead
{
    public const GRANULARITY_DAY = 'day';

    public const GRANULARITY_MONTH = 'month';

    /** @return array<string, mixed>|null */
    public static function field(array $raw, string $key): ?array
    {
        $field = $raw['fields'][$key] ?? null;

        return is_array($field) ? $field : null;
    }

    public static function isOk(?array $field): bool
    {
        return ($field['status'] ?? null) === 'OK';
    }

    /**
     * Comparable key of the EXP read: "Y-m-d" (granularity day) or "Y-m" (granularity month);
     * null when the date / granularity is missing or not a real date.
     */
    public static function expKey(?array $exp): ?string
    {
        $date = $exp['date'] ?? null;
        $granularity = $exp['granularity'] ?? null;
        if (! is_array($date) || ! isset($date['year'], $date['month'])) {
            return null;
        }
        $year = (int) $date['year'];
        $month = (int) $date['month'];

        if ($granularity === self::GRANULARITY_DAY && isset($date['day']) && checkdate($month, (int) $date['day'], $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, (int) $date['day']);
        }
        if ($granularity === self::GRANULARITY_MONTH && $month >= 1 && $month <= 12 && $year > 0) {
            return sprintf('%04d-%02d', $year, $month);
        }

        return null;
    }

    /** The reference EXP (any strtotime-able date) at the same granularity as the read. */
    public static function referenceKey(?string $date, ?string $granularity): ?string
    {
        if ($date === null || $date === '' || ($ts = strtotime($date)) === false) {
            return null;
        }

        return match ($granularity) {
            self::GRANULARITY_DAY => date('Y-m-d', $ts),
            self::GRANULARITY_MONTH => date('Y-m', $ts),
            default => null,
        };
    }

    /** "2029-11-18" -> "18/11/2029", "2029-11" -> "11/2029" (display only). */
    public static function displayKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }
        $parts = explode('-', $key);

        return implode('/', array_reverse($parts));
    }

    /** Batch = the 3 letters after "/" of the FG batch number (26X005/EKB -> EKB); null when there are none. */
    public static function batchLetters(?string $noBatch): ?string
    {
        $norm = (string) preg_replace('/[\s\/]+/', '/', strtoupper(trim((string) $noBatch)));

        return preg_match('/\/([A-Z]{3})$/', $norm, $m) === 1 ? $m[1] : null;
    }

    /** Photo numbers (1-based) the service says a field was read from, e.g. [0, 1] -> "foto 1 & 2". */
    public static function photosText(?array $field): ?string
    {
        $photos = collect($field['photos'] ?? [])->filter(fn ($p) => is_int($p))->map(fn (int $p) => $p + 1)->sort()->values();

        return $photos->isEmpty() ? null : 'foto '.$photos->implode(' & ');
    }

    /** Short read summary for ipc_logs.ocr_value, values as read: "batch=PLB; exp=EXP DATE 181129". */
    public static function summary(array $raw): ?string
    {
        $parts = [];
        foreach ($raw['fields'] ?? [] as $key => $field) {
            if (is_array($field) && ($field['value'] ?? null) !== null) {
                $parts[] = $key.'='.$field['value'];
            }
        }

        return $parts === [] ? null : mb_substr(implode('; ', $parts), 0, 250);
    }
}
