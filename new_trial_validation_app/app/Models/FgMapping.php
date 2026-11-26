<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Official FG code (iERP) and its verified match to a products master row.
 *
 * @property int $id
 * @property string $fg_code
 * @property string $item_name
 * @property string|null $item_name_previous
 * @property Carbon|null $effective_date
 * @property string $source
 * @property Carbon|null $imported_at
 * @property int|null $product_id
 * @property Carbon|null $verified_at
 * @property int|null $verified_by_user_id
 * @property string|null $notes
 * @property-read Product|null $product
 */
class FgMapping extends Model
{
    protected $table = 'vd_fg_mappings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'imported_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
