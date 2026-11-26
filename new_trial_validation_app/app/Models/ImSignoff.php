<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One slot of the QA sign-off chain of an IM revision (Prepared by / Review by
 * / Approved by), signed with the user's own password like Trial approval.
 *
 * @property int $id
 * @property int $revision_id
 * @property int $round
 * @property string $stage
 * @property string $role_label
 * @property int|null $user_id
 * @property string $user_name
 * @property string $status
 * @property string|null $comment
 * @property Carbon|null $signed_at
 */
class ImSignoff extends Model
{
    protected $table = 'vd_im_signoffs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    /** @return BelongsTo<ImRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ImRevision::class, 'revision_id');
    }
}
