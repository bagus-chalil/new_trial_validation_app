<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MasterTestType extends Model
{
    use SoftDeletes;

    public const CATEGORY_LEAKAGE = 'Leakage';

    public const CATEGORY_FUNCTIONAL = 'Functional';

    public const CATEGORY_ATTRIBUTE = 'Attribute';

    public const CATEGORIES = [
        self::CATEGORY_LEAKAGE,
        self::CATEGORY_FUNCTIONAL,
        self::CATEGORY_ATTRIBUTE,
    ];

    /**
     * Display names for the stored codes (names are legacy Power Apps column codes like
     * DROP_TEST_P). Anything not listed — e.g. a type an admin adds later — falls back to a
     * headline-cased version of its name.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'VACCUM' => 'Vaccum',
        'TORSI' => 'Torsi',
        'PRESS_TEST' => 'Press Test',
        'DROP_TEST_P' => 'Drop Test (Primary)',
        'DROP_TEST_S' => 'Drop Test (Secondary)',
        'SPRAY' => 'Spray',
        'FLIP_TOP' => 'Flip Top',
        'RUB_TEST' => 'Rub Test',
        'SWING_TEST' => 'Swing Test',
        'TAPE_TEST' => 'Tape Test',
        'HARDESS_TEST' => 'Hardess Test',
        'PUMP_TEST' => 'Pump Test',
        'SECURITY_SEAL' => 'Security Seal',
        'SHADE_LABEL' => 'Shade Label',
        'QR_CODE' => 'QR Code',
        'HOLOGRAM' => 'Hologram',
        'SHRINK' => 'Shrink',
        'BODY_LABEL' => 'Body Label',
        'BOTTOM_LABEL' => 'Bottom Label',
    ];

    protected $appends = ['label'];

    protected $fillable = [
        'name',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function labelFor(string $name): string
    {
        return self::LABELS[$name] ?? Str::headline(strtolower($name));
    }

    protected function label(): Attribute
    {
        return Attribute::get(fn () => self::labelFor((string) $this->name));
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
