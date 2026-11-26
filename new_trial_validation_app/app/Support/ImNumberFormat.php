<?php

namespace App\Support;

/**
 * IM number format: IM-{BRAND CODE}-{PRODUCT PART}[ V{n}], UPPER CASE with
 * single spaces. The first version has no suffix, revisions are V2, V3...
 * Paper IMs are written loosely ("IM-BHM- BHUMI ... 100ML- V2"), so every
 * comparison (register, IPC lookup) goes through normalize().
 */
final class ImNumberFormat
{
    public static function product(string $product): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtoupper($product)));
    }

    public static function compose(string $brandCode, string $productPart, int $version = 1): string
    {
        $number = 'IM-'.strtoupper(trim($brandCode)).'-'.self::product($productPart);

        return $version > 1 ? $number.' V'.$version : $number;
    }

    /** Canonical form of a loosely typed IM number; '' stays ''. */
    public static function normalize(?string $raw): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', mb_strtoupper((string) $raw)));
        if ($value === '') {
            return '';
        }

        // "IM- BHM - NAME" -> "IM-BHM-NAME"
        $value = (string) preg_replace('/^IM\s*-\s*([A-Z0-9]+)\s*-\s*/', 'IM-$1-', $value);
        // "100ML- V2" / "100ML -V2" / "100ML-V2" -> "100ML V2"
        $value = (string) preg_replace('/\s*-\s*(V\d+)$/', ' $1', $value);

        return $value;
    }
}
