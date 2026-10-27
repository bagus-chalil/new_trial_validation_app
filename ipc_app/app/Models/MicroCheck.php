<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MicroCheck extends Model
{
    /** The three result/std pairs on the paper form, keyed by the column prefix. */
    public const PARAMETERS = [
        'total_plate_count' => 'Total Plate Count',
        'yeast_mold' => 'Yeast & Mold',
        'pathogen' => 'Pathogen',
    ];

    /** Std (cfu/gr) values pre-filled on the form, taken from the paper form's usual entries. */
    public const DEFAULT_STD = [
        'total_plate_count' => '<100',
        'yeast_mold' => '<100',
        'pathogen' => '(-)',
    ];

    protected $fillable = [
        'ipc_batch_id',
        'user_id',
        'total_plate_count_result',
        'total_plate_count_std',
        'yeast_mold_result',
        'yeast_mold_std',
        'pathogen_result',
        'pathogen_std',
        'save_count',
    ];

    /** @return list<string> */
    public static function valueColumns(): array
    {
        return collect(array_keys(self::PARAMETERS))
            ->flatMap(fn (string $key) => ["{$key}_result", "{$key}_std"])
            ->all();
    }

    /** Every result and std filled in — the gate Finished Good's "Selesaikan" waits on. */
    public function isComplete(): bool
    {
        return collect(self::valueColumns())->every(fn (string $column) => filled($this->{$column}));
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IpcBatch::class, 'ipc_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(MicroCheckRevision::class);
    }
}
