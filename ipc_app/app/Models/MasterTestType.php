<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
