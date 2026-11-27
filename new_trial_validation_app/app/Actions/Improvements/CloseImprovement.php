<?php

namespace App\Actions\Improvements;

use App\Models\Improvement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Close an improvement as done ("Tandai selesai" in the old app) or reject it
 * ("Tolak usulan", with a mandatory reason). Both keep the history: the change
 * is appended to `feedback_log` and an activity row is written.
 */
class CloseImprovement
{
    /** Statuses after which the improvement is final and can no longer be closed or rejected. */
    public const FINAL_STATUSES = ['Selesai', 'Ditolak', 'Tidak Perlu Improvement'];

    public function __construct(private readonly LogImprovementActivity $log) {}

    public static function isOpen(Improvement $improvement): bool
    {
        return ! in_array($improvement->status, self::FINAL_STATUSES, true);
    }

    public function done(Improvement $improvement, User $user): Improvement
    {
        return $this->apply($improvement, $user, 'Selesai', 'CLOSE', null);
    }

    public function reject(Improvement $improvement, User $user, string $reason): Improvement
    {
        return $this->apply($improvement, $user, 'Ditolak', 'REJECT', trim($reason));
    }

    private function apply(Improvement $improvement, User $user, string $status, string $action, ?string $reason): Improvement
    {
        return DB::transaction(function () use ($improvement, $user, $status, $action, $reason) {
            $old = $improvement->only(['status', 'decision']);

            $improvement->status = $status;
            if ($reason !== null) {
                $improvement->pi_reason = $reason;
            }
            $improvement->updated_by_name = $user->name;

            $log = $improvement->feedback_log ?? [];
            $log[] = [
                'at' => Carbon::now()->toDateTimeString(),
                'by' => $user->name,
                'status' => $status,
                'decision' => $improvement->decision,
                'text' => $reason ?? (string) $improvement->pi_feedback,
            ];
            $improvement->feedback_log = $log;
            $improvement->save();

            ($this->log)($user, $action, $improvement, $old, $improvement->only(['status', 'decision']));

            return $improvement;
        });
    }
}
