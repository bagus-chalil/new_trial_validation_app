<?php

namespace App\Actions\Machines;

use App\Models\MachineLine;

/**
 * Line code for a newly added line when the maintainer doesn't supply one:
 * `{prefix}-{nn}` with the prefix taken from the category (SC, MU, MX, BN, PK, FR).
 */
class GenerateLineCode
{
    private const PREFIX = ['Skincare' => 'SC', 'Make Up' => 'MU', 'Fragrance' => 'FR', 'Mixing' => 'MX', 'Bignose' => 'BN', 'Packing Equipment' => 'PK'];

    public function __invoke(string $category): string
    {
        $prefix = self::PREFIX[$category] ?? 'LN';
        $n = MachineLine::withTrashed()->where('line_code', 'like', $prefix.'-%')->count() + 1;
        do {
            $code = $prefix.'-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $n++;
        } while (MachineLine::withTrashed()->where('line_code', $code)->exists());

        return $code;
    }
}
