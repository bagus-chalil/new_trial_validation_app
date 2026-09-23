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
            ['FG-0001', 'Sample Product A', 'BLK-0001'],
            ['FG-0001', 'Sample Product A', 'BLK-0002'],
            ['FG-0002', 'Sample Product B', 'BLK-0003'],
        ];
    }

    public function headings(): array
    {
        return ['FG Code', 'Nama Produk', 'Bulk Code'];
    }

    public function title(): string
    {
        return 'Master Produk';
    }
}
