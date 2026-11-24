<?php

namespace App\Services\Projects;

use App\Models\PurchaseOrder;
use App\Support\PurchaseOrderSettings;
use Carbon\CarbonInterface;

/**
 * Next PO number for a pattern such as `{NNN}/PO/PI/{RM}/{YYYY}`: the highest
 * existing sequence among numbers that match the same pattern for the same
 * month/year plus one, three digits. A new month or year changes the resolved
 * prefix/suffix, so the sequence restarts at 001 by itself (legacy behaviour).
 */
class PurchaseOrderNumbering
{
    public function next(CarbonInterface $date, ?string $pattern = null, ?int $ignoreId = null): string
    {
        $pattern = $pattern ?? (string) PurchaseOrderSettings::get()['pattern'];
        $base = PurchaseOrderSettings::resolvePattern($pattern, $date);

        $position = (int) strpos($base, '{NNN}');
        $prefix = substr($base, 0, $position);
        $suffix = substr($base, $position + 5);
        $regex = '/^'.preg_quote($prefix, '/').'(\d+)'.preg_quote($suffix, '/').'$/';

        $max = 0;
        PurchaseOrder::query()
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('po_no', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix).'%')
            ->pluck('po_no')
            ->each(function (string $no) use ($regex, &$max): void {
                if (preg_match($regex, $no, $m) === 1) {
                    $max = max($max, (int) $m[1]);
                }
            });

        return $prefix.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT).$suffix;
    }
}
