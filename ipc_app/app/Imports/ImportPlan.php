<?php

namespace App\Imports;

use App\Models\MasterImportIssue;

/**
 * Result of analysing an import file against the current DB state: which rows are valid, the
 * per-row issues, a preview of what committing would change, and the processor-specific
 * operations needed to actually apply it.
 */
class ImportPlan
{
    public int $totalRows = 0;

    public int $validRows = 0;

    /** @var list<array{row_number: int, severity: string, message: string, values: array<string, string>}> */
    public array $issues = [];

    /** @var array<string, int> */
    public array $preview = [];

    /** @var array<int|string, mixed> */
    public array $operations = [];

    /** @var array<int, true> */
    private array $errorRowNumbers = [];

    /** @var array<int, true> */
    private array $warningRowNumbers = [];

    /** @param array<string, string> $values */
    public function error(int $row, string $message, array $values): void
    {
        $this->issues[] = ['row_number' => $row, 'severity' => MasterImportIssue::SEVERITY_ERROR, 'message' => $message, 'values' => $values];
        $this->errorRowNumbers[$row] = true;
    }

    /** @param array<string, string> $values */
    public function warning(int $row, string $message, array $values): void
    {
        $this->issues[] = ['row_number' => $row, 'severity' => MasterImportIssue::SEVERITY_WARNING, 'message' => $message, 'values' => $values];
        $this->warningRowNumbers[$row] = true;
    }

    public function errorRows(): int
    {
        return count($this->errorRowNumbers);
    }

    public function warningRows(): int
    {
        return count(array_diff_key($this->warningRowNumbers, $this->errorRowNumbers));
    }
}
