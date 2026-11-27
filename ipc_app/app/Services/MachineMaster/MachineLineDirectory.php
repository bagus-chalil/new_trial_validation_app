<?php

namespace App\Services\MachineMaster;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only view of the Machine Master (`mc_lines`) owned by
 * new_trial_validation_app. IPC never writes to it; a master line only stores
 * the permanent `line_code` (SC-01, MX-SC01). If the master cannot be reached
 * (connection/table missing) every method degrades to "nothing available" so
 * IPC itself keeps working.
 */
class MachineLineDirectory
{
    public static function connectionName(): string
    {
        return (string) config('database.machine_master_connection', 'machine_master');
    }

    /**
     * Active lines for pickers, ordered by category then code.
     *
     * @return array<int, array{code: string, name: string, category: string, area: string|null, status: string}>
     */
    public static function options(): array
    {
        try {
            return DB::connection(self::connectionName())->table('mc_lines')
                ->whereNull('deleted_at')
                ->where('is_active', 1)
                ->orderBy('category')->orderBy('line_code')
                ->get(['line_code', 'name', 'category', 'area', 'status'])
                ->map(fn ($r) => [
                    'code' => $r->line_code, 'name' => $r->name, 'category' => $r->category,
                    'area' => $r->area, 'status' => $r->status,
                ])->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** Names keyed by line code, for showing the linked line next to a master line. */
    public static function namesByCode(): array
    {
        return collect(self::options())->mapWithKeys(fn ($o) => [$o['code'] => $o['name']])->all();
    }

    public static function exists(string $code): bool
    {
        try {
            return DB::connection(self::connectionName())->table('mc_lines')
                ->where('line_code', $code)->whereNull('deleted_at')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Suggests the master line code for an IPC line code like "SC 01", "LC 1",
     * "LS 2", "HP", "LP", "Eyeliner". Returns null when nothing safe matches
     * (e.g. Packing lines "MU 01", or the ambiguous mascara lines).
     */
    public static function suggest(string $ipcCode, array $knownCodes): ?string
    {
        $fixed = ['HP' => 'HP-01', 'LP' => 'LP-01', 'EYELINER' => 'EL-01'];
        $key = strtoupper(trim($ipcCode));
        if (isset($fixed[$key]) && in_array($fixed[$key], $knownCodes, true)) {
            return $fixed[$key];
        }
        if (preg_match('/^([A-Z]{2})[\s-]*0*(\d{1,2})$/', $key, $m)) {
            $candidate = $m[1].'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT);
            if ($m[1] !== 'MU' && in_array($candidate, $knownCodes, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
