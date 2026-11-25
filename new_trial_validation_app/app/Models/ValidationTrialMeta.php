<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Side table for trial fields v2.0 adds without altering trials_header: PM
 * and Bulk ready dates and the client approval evidence.
 *
 * @property int $trial_id
 * @property Carbon|null $pm_ready_date
 * @property Carbon|null $bulk_ready_date
 * @property bool $dates_defaulted
 * @property bool $client_approved
 * @property string|null $client_approval_note
 * @property Carbon|null $client_approved_at
 * @property int|null $client_approved_by_user_id
 */
class ValidationTrialMeta extends Model
{
    protected $table = 'vd_trial_meta';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pm_ready_date' => 'date:Y-m-d',
            'bulk_ready_date' => 'date:Y-m-d',
            'dates_defaulted' => 'boolean',
            'client_approved' => 'boolean',
            'client_approved_at' => 'datetime',
        ];
    }
}
