<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpcReportDocument extends Model
{
    public const KEY_FILLING = 'filling';

    public const KEY_PACKING = 'packing';

    public const KEY_FILLING_PACKING_FG = 'filling_packing_fg';

    public const REPORT_KEYS = [
        self::KEY_FILLING,
        self::KEY_PACKING,
        self::KEY_FILLING_PACKING_FG,
    ];

    protected $fillable = [
        'report_key',
        'doc_number',
        'revision',
        'title',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    public static function forKey(string $reportKey): ?self
    {
        return self::query()->where('report_key', $reportKey)->first();
    }
}
