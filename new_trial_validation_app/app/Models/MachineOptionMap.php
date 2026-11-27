<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Links one Trial `machine_used` option name to the machine master. `auto` and
 * `suggested` rows come from `machines:map-trial-options` and may be
 * recomputed; `manual` and `not_applicable` are admin decisions and are never
 * overwritten by the command.
 *
 * @property int $id
 * @property string $option_name
 * @property int|null $line_id
 * @property int|null $equipment_id
 * @property string $status
 */
class MachineOptionMap extends Model
{
    public const AUTO = 'auto';

    public const SUGGESTED = 'suggested';

    public const MANUAL = 'manual';

    public const NOT_APPLICABLE = 'not_applicable';

    /** Statuses an admin decided; the command never touches them. */
    public const LOCKED = [self::MANUAL, self::NOT_APPLICABLE];

    protected $table = 'mc_option_maps';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    /** @return BelongsTo<MachineLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(MachineLine::class, 'line_id')->withTrashed();
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id')->withTrashed();
    }

    /**
     * How many (non-deleted) trials use each machine_used name.
     *
     * @return array<string, int>
     */
    public static function usageCounts(): array
    {
        $counts = [];
        foreach (DB::table('trials_header')->whereNull('deleted_at')->whereNotNull('machine_used')->pluck('machine_used') as $raw) {
            $names = is_array($raw) ? $raw : json_decode((string) $raw, true);
            foreach (is_array($names) ? $names : [] as $name) {
                if (is_string($name) && $name !== '') {
                    $counts[$name] = ($counts[$name] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    /**
     * Every machine_used name that needs a mapping: the active options plus any
     * name a trial still uses (removed or renamed options).
     *
     * @param  array<string, int>|null  $usage
     * @return array<int, string>
     */
    public static function optionNames(?array $usage = null): array
    {
        $names = MasterOption::query()
            ->where('type', 'machine_used')
            ->whereNull('deleted_at')
            ->pluck('name')
            ->all();

        $names = array_merge($names, array_keys($usage ?? self::usageCounts()));
        $names = array_values(array_unique($names));
        natcasesort($names);

        return array_values($names);
    }

    /**
     * Line codes a trial's machine_used list resolves to (mapped rows only).
     *
     * @param  array<int, string>  $machineUsed
     * @return array<int, string>
     */
    public static function lineCodesFor(array $machineUsed): array
    {
        $lineIds = self::query()->whereIn('option_name', $machineUsed)->whereNotNull('line_id')->pluck('line_id');

        return MachineLine::withTrashed()->whereIn('id', $lineIds)->orderBy('line_code')->pluck('line_code')->unique()->values()->all();
    }
}
