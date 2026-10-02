<?php

namespace App\Services\Pdf;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Locale-aware formatting for the PDF/Excel exports.
 *
 * Dates mirror the frontend's formatDate() (lib/utils.ts): long month name
 * in the current UI language, 24-hour time, always Asia/Jakarta-local and
 * suffixed "WIB". The per-locale patterns live in lang/{locale}/exports.php
 * (`formats.*`).
 *
 * Stored workflow values (progress_status, review status, …) are shared with
 * the legacy app and never change — these helpers only pick a display label
 * for them, the same mapping the React pages use.
 */
class ExportFormat
{
    private const TRIAL_STATUS_KEYS = [
        'Draft' => 'draft',
        'In Review' => 'in_review',
        'Ready for Approval' => 'ready_for_approval',
        'Approved' => 'approved',
        'Need Revision' => 'need_revision',
        'Rejected' => 'rejected',
    ];

    public static function dateTime(mixed $value): string
    {
        $date = self::parse($value);

        return $date
            ? $date->translatedFormat(self::pattern('datetime')).' WIB'
            : self::fallback($value);
    }

    public static function date(mixed $value): string
    {
        $date = self::parse($value);

        return $date ? $date->translatedFormat(self::pattern('date')) : self::fallback($value);
    }

    /**
     * Display label for a stored trials_header.progress_status value (same
     * mapping as trialStatusLabel() in lib/trial-status.ts).
     */
    public static function trialStatus(?string $status): string
    {
        $key = self::TRIAL_STATUS_KEYS[$status ?? ''] ?? null;

        if ($key === null) {
            return $status ?: '-';
        }

        return self::line('common.status.'.$key);
    }

    /** Display label for a stored trials_review.status value (Pending/Reviewed). */
    public static function reviewStatus(?string $status): string
    {
        return match ($status) {
            'Pending' => self::line('report.review.statuses.pending'),
            'Reviewed' => self::line('report.review.statuses.reviewed'),
            default => $status ?: '-',
        };
    }

    /** Display label for a stored Line Configuration row trial_status (Pass/No Trial). */
    public static function lineTrialStatus(?string $status): string
    {
        return match ($status) {
            'Pass' => self::line('line_config.trial_status.pass'),
            'No Trial' => self::line('line_config.trial_status.no_trial'),
            default => $status ?: '-',
        };
    }

    private static function pattern(string $name): string
    {
        $pattern = __('exports.formats.'.$name);

        return is_string($pattern) ? $pattern : 'j M Y H:i';
    }

    private static function line(string $key): string
    {
        $line = __($key);

        return is_string($line) ? $line : $key;
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timezone = (string) config('app.timezone');

        try {
            $date = $value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)
                : CarbonImmutable::parse((string) $value, $timezone);
        } catch (Throwable) {
            return null;
        }

        $date = $date->setTimezone($timezone);

        // locale() with an argument returns the localized copy (its
        // no-argument getter form returns the locale string instead).
        $localized = $date->locale(app()->getLocale());

        return $localized instanceof CarbonImmutable ? $localized : $date;
    }

    private static function fallback(mixed $value): string
    {
        return is_scalar($value) && $value !== '' ? (string) $value : '-';
    }
}
