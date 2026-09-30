<?php

namespace App\Imports;

interface MasterImportProcessor
{
    /** Human label used in messages, e.g. "Master Produk". */
    public function label(): string;

    /** @return array<string, string> slugged heading key => label as written in the template */
    public function requiredHeadings(): array;

    /** @return array<string, string> value key => column label, for the issue list + error report */
    public function columns(): array;

    /**
     * Analyse the rows without writing anything.
     *
     * @param  list<array{row: int, data: array<string, string>}>  $rows
     * @param  callable(int $processed, int $total): void  $progress
     */
    public function plan(array $rows, callable $progress): ImportPlan;

    /**
     * Apply a plan's operations. Called inside a DB transaction by the runner.
     *
     * @param  callable(int $processed, int $total): void  $progress
     * @return array<string, int> actual counts, same keys as the plan's preview
     */
    public function apply(ImportPlan $plan, callable $progress): array;
}
