<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MasterProductsTemplateExport implements FromArray, WithHeadings, WithTitle
{
    public function array(): array
    {
        return [
            ['FG-0001', 'Sample Product A', 'Aktif', 'BLK-0001', 'BATCH-0001', 'Aktif'],
            ['FG-0001', 'Sample Product A', 'Aktif', 'BLK-0002', '', 'Aktif'],
            ['FG-0002', 'Sample Product B', 'Aktif', 'BLK-0003', '', 'Aktif'],
        ];
    }

    public function headings(): array
    {
        return ['FG Code', 'Nama Produk', 'Status Produk', 'Bulk Code', 'No Batch', 'Status Bulk Code'];
    }

    public function title(): string
    {
        return 'Master Produk';
    }
}
