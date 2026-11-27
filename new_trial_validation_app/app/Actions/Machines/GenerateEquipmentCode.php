<?php

namespace App\Actions\Machines;

use App\Models\Equipment;
use App\Models\MachineLine;

/**
 * Next unique equipment code for a line: `{line_code}-E{nn}` (SC-01-E03).
 * Soft-deleted rows count, so a code is never reused.
 */
class GenerateEquipmentCode
{
    public function __invoke(MachineLine $line): string
    {
        $base = $line->line_code.'-E';
        $n = Equipment::withTrashed()->where('line_id', $line->id)->count() + 1;
        do {
            $code = $base.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $n++;
        } while (Equipment::withTrashed()->where('equipment_code', $code)->exists());

        return $code;
    }
}
