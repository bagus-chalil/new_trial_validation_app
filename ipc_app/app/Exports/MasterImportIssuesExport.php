<?php

namespace App\Exports;

use App\Imports\MasterImportRunner;
use App\Models\MasterImport;
use App\Models\MasterImportIssue;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Every error/warning of one import, with the row's original values, so the user can fix the source file. */
class MasterImportIssuesExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private MasterImport $import) {}

    public function array(): array
    {
        $columns = array_keys(MasterImportRunner::processorFor($this->import->type)->columns());

        return $this->import->issues()
            ->orderByRaw("severity = 'error' desc")
            ->orderBy('row_number')
            ->get()
            ->map(fn (MasterImportIssue $issue) => [
                $issue->row_number,
                $issue->severity === MasterImportIssue::SEVERITY_ERROR ? 'Error (dilewati)' : 'Peringatan',
                $issue->message,
                ...array_map(fn (string $key) => $issue->values[$key] ?? '', $columns),
            ])
            ->all();
    }

    public function headings(): array
    {
        return ['Baris', 'Jenis', 'Keterangan', ...array_values(MasterImportRunner::processorFor($this->import->type)->columns())];
    }

    public function title(): string
    {
        return 'Laporan Import';
    }
}
