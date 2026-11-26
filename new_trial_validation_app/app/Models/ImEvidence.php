<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidence behind an IM revision: an Approved trial (approval of a trial is
 * evidence, never approval of the IM) or another official source document.
 *
 * @property int $id
 * @property int $revision_id
 * @property string $kind
 * @property int|null $trial_id
 * @property int|null $case_id
 * @property string $title
 * @property string $reference
 * @property string|null $note
 */
class ImEvidence extends Model
{
    protected $table = 'vd_im_evidence';

    protected $guarded = [];

    /** @return BelongsTo<Trial, $this> */
    public function trial(): BelongsTo
    {
        return $this->belongsTo(Trial::class, 'trial_id');
    }
}
