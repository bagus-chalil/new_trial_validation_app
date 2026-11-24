<?php

namespace App\Actions\Toolings;

use App\Models\Tooling;
use App\Models\ToolingApproval;
use App\Models\ToolingItem;
use App\Models\User;
use App\Services\Projects\ToolingWorkflow;
use App\Support\ToolingOptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The workflow steps of a Check Tooling after the request exists. Each public
 * method is one POST from the detail page; authorization (ToolingPolicy) and
 * the e-signature password (ToolingStepRequest) are checked before these run.
 * Every step re-derives the status from signatures + sample status
 * (ToolingWorkflow::deriveStatus) and logs to history_log + activity_logs.
 */
class ToolingSteps
{
    public function __construct(private readonly LogToolingActivity $log) {}

    /** PRNI "Prepared by" signature. */
    public function signPrni(Tooling $tooling, User $user, ?string $note): Tooling
    {
        return $this->step($tooling, $user, 'SIGN_PRNI', $note, function (Tooling $t) use ($user, $note) {
            $this->sign($t, 'prni', $user, $note);
        });
    }

    /** PRNI ships the physical sample to PIE. */
    public function sendSample(Tooling $tooling, User $user, string $date, ?string $note): Tooling
    {
        return $this->step($tooling, $user, 'SEND_SAMPLE', $note, function (Tooling $t) use ($date, $note) {
            $t->sample_status = 'Sampel Sedang Dikirim';
            $t->sample_send_date = Carbon::parse($date);
            $t->sample_send_note = $note;
        });
    }

    /** RNI fills the formula information and signs "Reviewed by". */
    public function signRni(Tooling $tooling, User $user, string $formulaInfo, string $volumeSuggest, ?string $note): Tooling
    {
        return $this->step($tooling, $user, 'SIGN_RNI', $note, function (Tooling $t) use ($user, $formulaInfo, $volumeSuggest, $note) {
            $t->formula_info = $formulaInfo;
            $t->volume_suggest = $volumeSuggest;
            $this->sign($t, 'rni', $user, $note);
        });
    }

    /** PIE confirms the physical sample arrived; starts the 5 working-day check SLA. */
    public function receiveSample(Tooling $tooling, User $user, string $date, ?string $note): Tooling
    {
        return $this->step($tooling, $user, 'RECEIVE_SAMPLE', $note, function (Tooling $t) use ($user, $date, $note) {
            $received = Carbon::parse($date);
            $t->sample_status = 'Sampel Diterima';
            $t->sample_received_date = $received;
            $t->sample_received_note = $note;
            $t->sample_received_by_user_id = $user->id;
            $t->sample_received_by_name = $user->name;
            $t->pi_owner_user_id = $user->id;
            $t->pi_owner_name = $user->name;
            $t->due_date = ToolingWorkflow::addWorkingDays($received, ToolingOptions::PIE_SLA_WORKING_DAYS);
        });
    }

    /**
     * PIE check result + PIE signature.
     *
     * @param  array{tools: array<int, array{tool?: string|null, actual?: string|null, status?: string|null}>, recommended_machine: string, need_invest: string, need_invest_note?: string|null, production_note?: string|null, risk_level: string, note?: string|null}  $data
     */
    public function pieCheck(Tooling $tooling, User $user, array $data): Tooling
    {
        return $this->step($tooling, $user, 'PIE_CHECK', $data['note'] ?? null, function (Tooling $t) use ($user, $data) {
            $t->recommended_machine = trim($data['recommended_machine']);
            $t->need_invest = $data['need_invest'];
            $t->need_invest_note = $data['need_invest_note'] ?? null;
            $t->production_note = $data['production_note'] ?? null;
            $t->risk_level = $data['risk_level'];
            $t->pi_owner_user_id = $user->id;
            $t->pi_owner_name = $user->name;
            $t->sample_status = 'Selesai Dicek';

            ToolingItem::where('tooling_id', $t->id)->delete();
            foreach (array_values($data['tools']) as $i => $row) {
                if (trim((string) ($row['tool'] ?? '')) === '' && trim((string) ($row['actual'] ?? '')) === '') {
                    continue;
                }
                ToolingItem::create([
                    'tooling_id' => $t->id,
                    'tool' => mb_substr(trim((string) ($row['tool'] ?? '')), 0, 160),
                    'actual' => mb_substr(trim((string) ($row['actual'] ?? '')), 0, 255),
                    'status' => (string) ($row['status'] ?? ''),
                    'sort_order' => $i,
                ]);
            }

            $this->sign($t, 'pi', $user, $data['note'] ?? null);
        });
    }

    /** Production review signature; the last one, which makes the request Approved. */
    public function signProduction(Tooling $tooling, User $user, ?string $note): Tooling
    {
        return $this->step($tooling, $user, 'SIGN_PRODUCTION', $note, function (Tooling $t) use ($user, $note) {
            $this->sign($t, 'production', $user, $note);
        });
    }

    /** "Revisi / Hold": sends the request back to PRNI with a reason; signing stops until released. */
    public function hold(Tooling $tooling, User $user, string $reason): Tooling
    {
        return $this->step($tooling, $user, 'HOLD', $reason, function (Tooling $t) use ($reason) {
            $t->status = ToolingOptions::STATUS_HOLD;
            $t->status_reason = $reason;
        }, derive: false);
    }

    public function reject(Tooling $tooling, User $user, string $reason): Tooling
    {
        return $this->step($tooling, $user, 'REJECT', $reason, function (Tooling $t) use ($reason) {
            $t->status = ToolingOptions::STATUS_REJECTED;
            $t->status_reason = $reason;
        }, derive: false);
    }

    /** Releases a hold: the status goes back to what the signatures imply. */
    public function resume(Tooling $tooling, User $user, ?string $note): Tooling
    {
        return $this->step($tooling, $user, 'RESUME', $note, function (Tooling $t) {
            $t->status_reason = null;
            $t->status = ToolingWorkflow::signedStatus($t);
        }, derive: false);
    }

    /**
     * @param  callable(Tooling): void  $change
     */
    private function step(Tooling $tooling, User $user, string $action, ?string $note, callable $change, bool $derive = true): Tooling
    {
        return DB::transaction(function () use ($tooling, $user, $action, $note, $change, $derive) {
            $oldStatus = $tooling->status;
            $change($tooling);
            $tooling->unsetRelation('approvals');

            if ($derive) {
                $tooling->status = ToolingWorkflow::deriveStatus($tooling);
            }
            if ($tooling->status === ToolingOptions::STATUS_APPROVED) {
                $tooling->sample_status = 'Selesai Dicek';
            }

            ($this->log)($user, $action, $tooling, $note, ['status' => $oldStatus], ['status' => $tooling->status]);
            $tooling->save();

            return $tooling;
        });
    }

    private function sign(Tooling $tooling, string $role, User $user, ?string $note): void
    {
        ToolingApproval::updateOrCreate(
            ['tooling_id' => $tooling->id, 'role' => $role],
            [
                'approved' => true,
                'signer_user_id' => $user->id,
                'signer_name' => $user->name,
                'sign_date' => Carbon::today(),
                'signed_at' => Carbon::now(),
                'note' => $note,
                'signature_method' => 'password',
                'signature_image' => null,
                'signed_by_account' => mb_substr((string) $user->email, 0, 100),
            ],
        );
    }
}
