<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Trial Validation v2.0 Tahap 6: the IM master, one per official FG Code. The
 * quality standard itself lives in its revisions (ImRevision).
 *
 * @property int $id
 * @property string $fg_code
 * @property string $brand_code
 * @property string $product_part
 * @property int|null $product_id
 * @property string $product_name
 * @property string $customer
 * @property list<string>|null $bulk_wip_refs
 * @property int|null $created_by_user_id
 * @property string $created_by_name
 * @property Carbon|null $created_at
 */
class ImMaster extends Model
{
    protected $table = 'vd_im_masters';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['bulk_wip_refs' => 'array'];
    }

    /** @return HasMany<ImRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ImRevision::class, 'master_id')->orderByDesc('revision_no');
    }
}
