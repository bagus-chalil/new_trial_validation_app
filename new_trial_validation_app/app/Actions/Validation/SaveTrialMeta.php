<?php

namespace App\Actions\Validation;

use App\Models\Trial;
use App\Models\User;
use App\Models\ValidationTrialMeta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Writes the v2.0 side-table fields of a trial (vd_trial_meta): PM and Bulk
 * ready dates from the trial form, and the client approval evidence from the
 * Product Journey. trials_header is never touched.
 */
class SaveTrialMeta
{
    /**
     * @param  array{pm_ready_date?: string|null, bulk_ready_date?: string|null}  $dates
     */
    public function saveDates(Trial $trial, array $dates): void
    {
        if (! Schema::hasTable('vd_trial_meta')) {
            return;
        }

        $pm = $dates['pm_ready_date'] ?? null;
        $bulk = $dates['bulk_ready_date'] ?? null;
        $meta = ValidationTrialMeta::query()->where('trial_id', $trial->id)->first();

        if ($meta === null && $pm === null && $bulk === null) {
            return;
        }

        $meta ??= new ValidationTrialMeta(['trial_id' => $trial->id]);

        // Untouched defaulted placeholders stay flagged; a user-entered value replaces them.
        if ($pm !== null || $bulk !== null || ! $meta->dates_defaulted) {
            $meta->pm_ready_date = $pm === null ? null : Carbon::parse($pm);
            $meta->bulk_ready_date = $bulk === null ? null : Carbon::parse($bulk);
            $meta->dates_defaulted = false;
        }

        $meta->save();
    }

    public function saveClientApproval(Trial $trial, bool $approved, ?string $note, User $by): ValidationTrialMeta
    {
        $meta = ValidationTrialMeta::query()->firstOrNew(['trial_id' => $trial->id]);

        $meta->client_approved = $approved;
        $meta->client_approval_note = $approved ? $note : null;
        $meta->client_approved_at = $approved ? Carbon::now() : null;
        $meta->client_approved_by_user_id = $approved ? $by->id : null;
        $meta->save();

        return $meta;
    }
}
