<?php

namespace App\Services\Ai;

/** Median/MAD helpers: statistics that one bad value cannot move. */
class RobustStats
{
    /** MAD -> sigma equivalent for normal data. */
    public const MAD_SCALE = 1.4826;

    /** @param  list<float>  $values */
    public static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /** @param  list<float>  $values */
    public static function mad(array $values, float $median): float
    {
        return self::median(array_map(fn ($v) => abs($v - $median), $values));
    }
}
