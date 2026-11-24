<?php

namespace App\Actions\Toolings;

use App\Actions\Projects\SaveProject;
use App\Models\Project;
use App\Models\Tooling;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "Need Invest = YES" continues as a "Tooling Investment" project (old app:
 * createProjectFromTooling()). PIC is the PIE owner when still an active user,
 * else the user promoting it. One tooling becomes at most one project.
 */
class PromoteToolingToProject
{
    public function __construct(
        private readonly SaveProject $saveProject,
        private readonly LogToolingActivity $log,
    ) {}

    public function __invoke(Tooling $tooling, User $user): Project
    {
        if ($tooling->project_id && Project::whereNull('deleted_at')->whereKey($tooling->project_id)->exists()) {
            throw new InvalidArgumentException(__('toolings.messages.already_promoted'));
        }

        return DB::transaction(function () use ($tooling, $user) {
            $picId = $user->id;
            if ($tooling->pi_owner_user_id
                && User::query()->where('is_active', 1)->whereNull('deleted_at')->whereKey($tooling->pi_owner_user_id)->exists()) {
                $picId = (int) $tooling->pi_owner_user_id;
            }

            $tools = $tooling->items
                ->filter(fn ($item) => $item->tool !== '' || $item->actual !== '')
                ->map(fn ($item) => '- '.$item->tool.': '.($item->actual !== '' ? $item->actual : '-').' ['.($item->status !== '' ? $item->status : '-').']')
                ->implode("\n");

            $description = implode("\n", [
                'NPD Request No: '.($tooling->npd_no ?: '-'),
                'Client / Product: '.($tooling->client_code ?: '-').' - '.($tooling->product_name ?: '-'),
                'Packaging Type: '.($tooling->packaging_type ?: '-').' (BOM: '.($tooling->bom_primary ?: '-').')',
                'Recommended Machine/Line: '.($tooling->recommended_machine ?: '-'),
                'Need Invest: '.($tooling->need_invest ?: '-').' ('.($tooling->need_invest_note ?: '-').')',
                'Risk Level: '.($tooling->risk_level ?: '-'),
                '',
                'Hasil Cek Tooling:',
                $tools !== '' ? $tools : '-',
                '',
                'Catatan Produksi:',
                $tooling->production_note ?: '-',
            ]);

            $priority = match (true) {
                $tooling->risk_level === 'High risk' || $tooling->is_critical => 'High',
                $tooling->risk_level === 'Medium risk' => 'Medium',
                default => 'Low',
            };

            $project = ($this->saveProject)(null, [
                'name' => mb_substr('Tooling Investment · '.($tooling->product_name ?: $tooling->npd_no).' ('.($tooling->packaging_type ?: 'Packaging').')', 0, 255),
                'category' => 'Tooling NPD',
                'status' => 'Not Started',
                'priority' => $priority,
                'pic_user_id' => $picId,
                'area' => 'PRNI / Production',
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->addDays(45)->toDateString(),
                'description' => $description,
            ], $user);

            $tooling->project_id = $project->id;
            ($this->log)($user, 'PROMOTE', $tooling, $project->code ?: $project->name, null, ['project_id' => $project->id]);
            $tooling->save();

            return $project;
        });
    }
}
