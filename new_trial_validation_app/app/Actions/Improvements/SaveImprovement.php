<?php

namespace App\Actions\Improvements;

use App\Models\Improvement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaveImprovement
{
    public function __construct(private readonly LogImprovementActivity $log) {}

    /**
     * @param  array<string, mixed>  $data  validated input from SaveImprovementRequest
     */
    public function __invoke(?Improvement $improvement, array $data, User $user): Improvement
    {
        return DB::transaction(function () use ($improvement, $data, $user) {
            $links = collect(preg_split('/\R/', (string) ($data['links'] ?? '')) ?: [])
                ->map(fn ($l) => trim((string) $l))
                ->filter()
                ->values()
                ->all();

            $attributes = [
                'title' => trim($data['title']),
                'department' => trim((string) ($data['department'] ?? '')),
                'type' => trim((string) ($data['type'] ?? '')),
                'issue' => $data['issue'] ?? null,
                'background' => $data['background'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'goals' => $data['goals'] ?? null,
                'saving_mp' => trim((string) ($data['saving_mp'] ?? '')),
                'quality_metric' => trim((string) ($data['quality_metric'] ?? '')),
                'links' => $links,
                'updated_by_name' => $user->name,
            ];

            if (! $improvement) {
                $improvement = new Improvement;
                $improvement->fill($attributes + [
                    'submitted_by_user_id' => $user->id,
                    'submitted_by_name' => $user->name,
                    'submitted_at' => Carbon::now(),
                    'status' => 'Baru',
                    'decision' => 'Pending',
                    'feedback_log' => [],
                ])->save();
                ($this->log)($user, 'CREATE', $improvement, null, ['title' => $improvement->title]);

                return $improvement;
            }

            $old = $improvement->only(['title', 'type', 'department']);
            $improvement->fill($attributes)->save();
            ($this->log)($user, 'UPDATE', $improvement, $old, $improvement->only(array_keys($old)));

            return $improvement;
        });
    }
}
