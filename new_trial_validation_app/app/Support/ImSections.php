<?php

namespace App\Support;

/**
 * Sections of the IM worksheet, mirroring the paper IM (FR.QAC.050.01):
 * A.1 BOM + filling / appearance points, A.2 packing and coding, A.3 filling
 * weight, B surveillance. `numeric` sections carry value_min / value_max.
 */
final class ImSections
{
    /** @var array<string, array{numeric: bool}> */
    public const ALL = [
        'bom' => ['numeric' => false],
        'filling_point' => ['numeric' => false],
        'appearance_point' => ['numeric' => false],
        'weight' => ['numeric' => true],
        'coding' => ['numeric' => false],
        'packing_point' => ['numeric' => false],
        'surveillance_initial' => ['numeric' => false],
        'surveillance_periodic' => ['numeric' => false],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * Free-form blocks stored in vd_im_revisions.content.
     *
     * @return list<string>
     */
    public static function contentKeys(): array
    {
        return ['master_box_qty', 'pallet_layout', 'shipper_format', 'batch_format_tube', 'batch_format_unit_box', 'batch_format_master_box', 'legal_note', 'notes'];
    }
}
