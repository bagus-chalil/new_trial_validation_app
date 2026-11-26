<?php

namespace App\Actions\ImMaster;

use App\Models\ImEvidence;
use App\Models\ImMaster;
use App\Models\ImNumber;
use App\Models\ImParameter;
use App\Models\ImRevision;
use App\Models\User;
use App\Support\ImLog;
use App\Support\ImNumberFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens the next revision of an IM master as a Draft, copying the latest
 * revision (worksheet, parameters, evidence) so QA only changes what changed.
 * The previous revision stays Effective until the new one is approved and
 * released, so the old number is NOT superseded here (ImRelease does that).
 */
class CreateImRevision
{
    public function __invoke(ImMaster $master, string $reason, User $user): ImRevision
    {
        return DB::transaction(function () use ($master, $reason, $user) {
            ImMaster::query()->lockForUpdate()->findOrFail($master->id);

            if (ImRevision::query()->where('master_id', $master->id)->whereIn('status', ImRevision::OPEN)->exists()) {
                throw ValidationException::withMessages(['revision_reason' => __('im_master.error.open_revision')]);
            }

            $base = ImRevision::query()->where('master_id', $master->id)->orderByDesc('revision_no')->firstOrFail();
            $effective = ImRevision::query()->where('master_id', $master->id)->where('status', 'Effective')->first();

            $version = $base->revision_no + 1;
            $number = ImNumberFormat::compose($master->brand_code, $master->product_part, $version);
            if (ImNumber::query()->where('number', $number)->exists()) {
                throw ValidationException::withMessages(['revision_reason' => __('numbers.im.duplicate', ['number' => $number])]);
            }

            $replaces = $effective ?? $base;

            $register = ImNumber::create([
                'number' => $number,
                'brand_code' => $master->brand_code,
                'product_part' => $master->product_part,
                'version_no' => $version,
                'replaces_id' => $replaces->im_number_id,
                'replaces_number' => $replaces->im_number,
                'revision_reason' => trim($reason),
                'issue_date' => now()->toDateString(),
                'product_id' => $master->product_id,
                'fg_codes' => [$master->fg_code],
                'line_machine' => $base->line_label,
                'customer' => $master->customer,
                'customer_ref' => $base->customer_ref,
                'status' => 'Active',
                'issued_by_user_id' => $user->id,
                'issued_by_name' => (string) ($user->name ?: $user->email),
            ]);

            $revision = ImRevision::create([
                'master_id' => $master->id,
                'revision_no' => $version,
                'im_number' => $number,
                'im_number_id' => $register->id,
                'status' => 'Draft',
                'description' => $base->description,
                'appearance' => $base->appearance,
                'mfg_category' => $base->mfg_category,
                'customer' => $base->customer,
                'customer_ref' => $base->customer_ref,
                'line_label' => $base->line_label,
                'issue_date' => now()->toDateString(),
                'effective_from' => now()->toDateString(),
                'revision_reason' => trim($reason),
                'replaces_number' => $replaces->im_number,
                'content' => $base->content,
                'prepared_by_user_id' => $user->id,
                'prepared_by_name' => (string) ($user->name ?: $user->email),
                'review_round' => 1,
            ]);

            foreach ($base->parameters as $p) {
                ImParameter::create($p->only(['section', 'sort_order', 'code', 'name', 'category', 'description', 'specification', 'frequency', 'value_min', 'value_max', 'unit']) + ['revision_id' => $revision->id]);
            }
            foreach ($base->evidence as $e) {
                ImEvidence::create($e->only(['kind', 'trial_id', 'case_id', 'title', 'reference', 'note']) + ['revision_id' => $revision->id, 'added_by_user_id' => $user->id]);
            }

            ImLog::write($user, 'CREATE_REVISION', $revision, ['replaces' => $replaces->im_number, 'reason' => $reason]);

            return $revision;
        });
    }
}
