<?php

namespace App\Actions\Improvements;

use App\Models\Improvement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** PI review: status, decision and feedback; every change is appended to `feedback_log`. */
class ReviewImprovement
{
    public function __construct(private readonly LogImprovementActivity $log) {}

    /**
     * @param  array<string, mixed>  $data  validated input from ReviewImprovementRequest
     */
    public function __invoke(Improvement $improvement, array $data, User $user): Improvement
    {
        return DB::transaction(function () use ($improvement, $data, $user) {
            $old = $improvement->only(['status', 'decision']);

            $improvement->fill([
                'status' => $data['status'],
                'decision' => $data['decision'],
                'pi_owner' => trim((string) ($data['pi_owner'] ?? '')) ?: $user->name,
                'pi_feedback' => $data['pi_feedback'] ?? null,
                'pi_suggestion' => $data['pi_suggestion'] ?? null,
                'pi_action' => $data['pi_action'] ?? null,
                'pi_reason' => $data['pi_reason'] ?? null,
                'updated_by_name' => $user->name,
            ]);

            $log = $improvement->feedback_log ?? [];
            $log[] = [
                'at' => Carbon::now()->toDateTimeString(),
                'by' => $user->name,
                'status' => $data['status'],
                'decision' => $data['decision'],
                'text' => (string) ($data['pi_feedback'] ?? ''),
            ];
            $improvement->feedback_log = $log;
            $improvement->save();

            ($this->log)($user, 'REVIEW', $improvement, $old, $improvement->only(['status', 'decision']));

            return $improvement;
        });
    }
}
