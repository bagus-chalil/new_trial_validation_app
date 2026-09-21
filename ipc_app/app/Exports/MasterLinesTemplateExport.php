<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MasterLinesTemplateExport implements FromArray, WithHeadings, WithTitle
{
    public function array(): array
    {
        return [
            ['Packing', 'Make Up', 'MU 01', 'Make Up 01', 'Aktif'],
            ['Packing', 'Make Up', 'MU 02', 'Make Up 02', 'Aktif'],
        ];
    }

    public function headings(): array
    {
        return ['Kategori', 'Area', 'Kode Line', 'Nama Line', 'Status Line'];
    }

    public function title(): string
    {
        return 'Master Line';
    }
}
