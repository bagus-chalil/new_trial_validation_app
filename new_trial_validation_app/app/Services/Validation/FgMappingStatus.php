<?php

namespace App\Services\Validation;

use App\Models\FgMapping;
use App\Models\Product;

/**
 * Whether a product's finish-good code is backed by a verified iERP mapping.
 *   verified   official code imported and a person confirmed it for this product
 *   unverified official code exists but nobody confirmed the match yet
 *   mismatch   the code is mapped to a different product
 *   missing    no FG code, "N/A", or a code iERP never listed
 * A Case without a product (FG not available yet) is "missing" too, which is
 * allowed: the brief lets a trial start before the FG exists.
 */
final class FgMappingStatus
{
    public const VERIFIED = 'verified';

    public const UNVERIFIED = 'unverified';

    public const MISMATCH = 'mismatch';

    public const MISSING = 'missing';

    /** @return array{status: string, fg_code: string|null, official_name: string|null} */
    public static function forProduct(?Product $product): array
    {
        $code = $product === null ? '' : trim((string) $product->finish_good_code);

        if ($code === '' || in_array(strtoupper($code), ['N/A', '0'], true)) {
            return ['status' => self::MISSING, 'fg_code' => null, 'official_name' => null];
        }

        $mapping = FgMapping::query()->where('fg_code', $code)->first();

        if ($mapping === null) {
            return ['status' => self::MISSING, 'fg_code' => $code, 'official_name' => null];
        }

        $sameProduct = $mapping->product_id !== null && (int) $mapping->product_id === (int) $product?->id;

        $status = match (true) {
            $mapping->product_id !== null && ! $sameProduct => self::MISMATCH,
            $mapping->verified_at !== null && $sameProduct => self::VERIFIED,
            default => self::UNVERIFIED,
        };

        return ['status' => $status, 'fg_code' => $code, 'official_name' => $mapping->item_name];
    }
}
