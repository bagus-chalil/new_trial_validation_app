<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveProject
{
    public function __construct(private readonly LogProjectActivity $log) {}

    /**
     * @param  array<string, mixed>  $data  validated input from SaveProjectRequest
     */
    public function __invoke(?Project $project, array $data, User $user): Project
    {
        return DB::transaction(function () use ($project, $data, $user) {
            $picId = isset($data['pic_user_id']) ? (int) $data['pic_user_id'] : null;
            $pic = $picId ? User::find($picId) : null;

            $attributes = [
                'name' => trim($data['name']),
                'code' => trim((string) ($data['code'] ?? '')),
                'category' => trim($data['category']),
                'status' => $data['status'],
                'priority' => $data['priority'],
                'pic_user_id' => $pic?->id,
                'area' => trim((string) ($data['area'] ?? '')),
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'description' => $data['description'] ?? null,
                'vendor' => trim((string) ($data['vendor'] ?? '')),
                'po' => trim((string) ($data['po'] ?? '')),
                'updated_by_user_id' => $user->id,
                'updated_by_name' => $user->name,
            ];

            // PIC name text: follow the chosen user, otherwise keep what was
            // there (legacy imports may only have a text name).
            if ($pic) {
                $attributes['pic_name'] = $pic->name;
            } elseif (! $project) {
                $attributes['pic_name'] = '';
            }

            if (! $project) {
                $project = new Project;
                $attributes['created_by_user_id'] = $user->id;
                $attributes['created_by_name'] = $user->name;
                $attributes['progress'] = (int) ($data['progress'] ?? 0);
                $project->fill($attributes)->save();
                ($this->log)($user, 'CREATE', $project->id, $project->name, null, ['name' => $project->name, 'status' => $project->status]);

                return $project;
            }

            $old = $project->only(['name', 'status', 'priority', 'pic_name', 'start_date', 'end_date']);

            // Progress is only hand-editable while there are no stages to derive it from.
            if (! $project->stages()->exists() && array_key_exists('progress', $data) && $data['progress'] !== null) {
                $attributes['progress'] = (int) $data['progress'];
            }

            $project->fill($attributes)->save();
            ($this->log)($user, 'UPDATE', $project->id, $project->name, $old, $project->only(array_keys($old)));

            return $project;
        });
    }
}
