<?php

namespace App\Services\Validation;

use App\Models\ImNumber;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Support\ImNumberFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * READ-ONLY lookup of IPC batches for the Product Journey. ipc_app is a
 * separate project (own DB `ipc_system`, no shared auth), so there is no
 * foreign key: a batch is matched to a Case by business key only.
 *
 *  - IM: the batch's startup_checks.im_document_number equals (after
 *    normalising spaces/case) an IM number linked to the Case.
 *  - FG: master_products.fg_code equals the Case product's finish_good_code or
 *    an FG code stored on a linked IM (ignored when empty / N/A: the product
 *    master has 25 groups of FG "N/A").
 *
 * Never writes, never throws: when the IPC database is unreachable or has a
 * different shape the Journey simply shows "not available".
 */
class IpcBatchLookup
{
    public const CONNECTION = 'ipc_system';

    private const LIMIT = 50;

    /** @var list<string> */
    private const NO_CODE = ['', 'N/A', 'NA', '-'];

    /**
     * @return array{available: bool, batches: list<array<string, mixed>>}
     */
    public function forCase(ValidationCase $case): array
    {
        try {
            return $this->lookup($case);
        } catch (Throwable $e) {
            Log::info('IPC lookup unavailable: '.$e->getMessage());

            return ['available' => false, 'batches' => []];
        }
    }

    /**
     * @return array{available: bool, batches: list<array<string, mixed>>}
     */
    private function lookup(ValidationCase $case): array
    {
        $case->loadMissing('product');

        $ims = ImNumber::query()
            ->whereIn('number', ValidationCaseLink::query()->where('case_id', $case->id)->where('kind', 'im')->pluck('ref_key'))
            ->get();

        $imNumbers = $ims->pluck('number')->map(fn ($n) => ImNumberFormat::normalize($n))->filter()->values()->all();

        $fgCodes = collect([$case->product?->finish_good_code])
            ->merge($ims->flatMap(fn (ImNumber $i) => $i->fg_codes ?? []))
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->reject(fn ($c) => in_array($c, self::NO_CODE, true))
            ->unique()
            ->values()
            ->all();

        if ($imNumbers === [] && $fgCodes === []) {
            // Nothing to match on; still report that the IPC DB is reachable.
            DB::connection(self::CONNECTION)->getPdo();

            return ['available' => true, 'batches' => []];
        }

        $productParts = $ims->pluck('product_part')->filter()->unique()->values()->all();

        $query = DB::connection(self::CONNECTION)->table('ipc_batches as b')
            ->join('master_products as p', 'p.id', '=', 'b.master_product_id')
            ->leftJoin('startup_checks as sc', 'sc.ipc_batch_id', '=', 'b.id')
            ->whereNull('b.deleted_at')
            ->where(function ($w) use ($fgCodes, $productParts) {
                if ($fgCodes !== []) {
                    $w->orWhereIn(DB::raw('UPPER(TRIM(p.fg_code))'), $fgCodes);
                }
                foreach ($productParts as $part) {
                    $w->orWhere('sc.im_document_number', 'like', '%'.addcslashes($part, '%_\\').'%');
                }
            })
            ->orderByDesc('b.id')
            ->limit(self::LIMIT * 3);

        $rows = $query->get([
            'b.id', 'b.no_batch', 'b.current_stage', 'b.flow_type', 'b.created_at', 'b.mixing_date', 'b.exp_date',
            'p.fg_code', 'p.product_name', 'sc.im_document_number',
        ]);

        $out = [];
        foreach ($rows as $row) {
            $imDoc = ImNumberFormat::normalize($row->im_document_number);
            $byIm = $imDoc !== '' && in_array($imDoc, $imNumbers, true);
            $byFg = in_array(strtoupper(trim((string) $row->fg_code)), $fgCodes, true);

            if (! $byIm && ! $byFg) {
                continue;
            }

            $out[] = [
                'id' => (int) $row->id,
                'no_batch' => (string) $row->no_batch,
                'stage' => (string) $row->current_stage,
                'flow_type' => $row->flow_type,
                'created_at' => $row->created_at === null ? null : substr((string) $row->created_at, 0, 10),
                'mixing_date' => $row->mixing_date === null ? null : substr((string) $row->mixing_date, 0, 10),
                'exp_date' => $row->exp_date === null ? null : substr((string) $row->exp_date, 0, 10),
                'fg_code' => (string) $row->fg_code,
                'product_name' => (string) $row->product_name,
                'im_document_number' => $row->im_document_number,
                'matched_by' => $byIm ? 'im' : 'fg',
            ];

            if (count($out) >= self::LIMIT) {
                break;
            }
        }

        return ['available' => true, 'batches' => $out];
    }
}
