<?php

namespace App\Services\Vision;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * EXP = MFD + shelf-life (months), both as DDMMYY strings (the format printed on the tube).
 * When the MFD's day doesn't exist in the target month (e.g. 31 Jan + 1 month) this throws
 * instead of clamping or rolling over — how a SKU's EXP handles that is a business rule QC
 * must confirm, so the evaluator routes it to REVIEW rather than guessing a date.
 */
class ExpDateCalculator
{
    public function fromMfd(string $mfdDdmmyy, int $shelfLifeMonths): string
    {
        $mfd = $this->parseDdmmyy($mfdDdmmyy);

        if ($shelfLifeMonths < 1) {
            throw new InvalidArgumentException('Shelf-life harus minimal 1 bulan.');
        }

        $targetMonth = $mfd->startOfMonth()->addMonths($shelfLifeMonths);

        if ($mfd->day > $targetMonth->daysInMonth) {
            throw new InvalidArgumentException(
                "Tanggal {$mfd->day} tidak ada di bulan {$targetMonth->format('m/Y')} - cek manual."
            );
        }

        return $targetMonth->setDay($mfd->day)->format('dmy');
    }

    /**
     * EXP date for a batch = mixing date + the product's shelf-life in months (same day of
     * month). Returns Y-m-d, or null when it cannot be derived safely: bad date, no shelf-life,
     * or the day does not exist in the target month (31 Jan + 1 month) — never rolled over or
     * clamped, the operator then types the EXP date by hand (business rule QC must confirm).
     */
    public function expDateFromMixing(?string $mixingDate, ?int $shelfLifeMonths): ?string
    {
        if (! $mixingDate || ! $shelfLifeMonths || $shelfLifeMonths < 1) {
            return null;
        }

        try {
            $mixing = CarbonImmutable::createFromFormat('Y-m-d', substr($mixingDate, 0, 10));
        } catch (\Throwable) {
            return null;
        }

        if (! $mixing || $mixing->format('Y-m-d') !== substr($mixingDate, 0, 10)) {
            return null;
        }

        $target = $mixing->startOfMonth()->addMonths($shelfLifeMonths);

        return $mixing->day > $target->daysInMonth ? null : $target->setDay($mixing->day)->format('Y-m-d');
    }

    public function parseDdmmyy(string $ddmmyy): CarbonImmutable
    {
        if (! preg_match('/^(\d{2})(\d{2})(\d{2})$/', $ddmmyy, $m)) {
            throw new InvalidArgumentException("'{$ddmmyy}' bukan format DDMMYY.");
        }

        [, $day, $month, $year] = array_map('intval', $m);
        $year += 2000;

        if (! checkdate($month, $day, $year)) {
            throw new InvalidArgumentException("'{$ddmmyy}' bukan tanggal yang valid.");
        }

        return CarbonImmutable::create($year, $month, $day);
    }
}
