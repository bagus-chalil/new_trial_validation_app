<?php

namespace App\Actions\StartupChecks;

use App\Actions\Vision\RecordStartupAiFeedback;
use App\Models\IpcBatch;
use App\Models\MasterProduct;
use App\Models\StartupCheck;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveStartupCheck
{
    public function handle(IpcBatch $batch, User $user, array $data): StartupCheck
    {
        // Mixing Date, Exp Date, and Line are chosen here (not at batch creation, see
        // IpcBatchController) but they're batch-level fields, not startup_checks columns —
        // route them onto the batch.
        $mixingDate = Arr::pull($data, 'mixing_date');
        $expDate = Arr::pull($data, 'exp_date');
        $shelfLifeMonths = Arr::pull($data, 'shelf_life_months');
        $packagingType = Arr::pull($data, 'primary_packaging_type');
        $masterLineId = Arr::pull($data, 'master_line_id');
        // Which "Pakai saran" buttons the operator used (comma separated form-field names); only
        // used to label the AI accuracy feedback row, never stored on the check itself.
        $aiApplied = array_values(array_filter(explode(',', (string) Arr::pull($data, 'ai_applied'))));

        // N/A wins over any number still sitting in the input — never store both.
        $data['density_not_applicable'] = (bool) ($data['density_not_applicable'] ?? false);
        if ($data['density_not_applicable']) {
            $data['density'] = null;
        }

        // Packing + FG has no filling, so these four are never captured there.
        if ($batch->flow_type === IpcBatch::FLOW_PACKING_FG) {
            $data['filling_range_min'] = null;
            $data['filling_range_max'] = null;
            $data['density'] = null;
            $data['density_not_applicable'] = false;
            $data['average_of_empty_bottle_weight'] = null;
        }

        foreach (['primary', 'secondary', 'tersier'] as $tier) {
            $data["coding_operator_na_{$tier}"] = (bool) ($data["coding_operator_na_{$tier}"] ?? false);
        }

        $startupCheck = DB::transaction(function () use ($batch, $user, $data, $mixingDate, $expDate, $shelfLifeMonths, $packagingType, $masterLineId) {
            $startupCheck = StartupCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [
                    ...$data,
                    'user_id' => $user->id,
                    'completed_at' => now(),
                ],
            );

            $batch->update([
                'mixing_date' => $mixingDate,
                'exp_date' => $expDate,
                'shelf_life_months' => $shelfLifeMonths,
                'primary_packaging_type' => $packagingType,
                'master_line_id' => $masterLineId,
                ...($batch->current_stage === IpcBatch::STAGE_STARTUP ? ['current_stage' => $batch->nextStageAfter(IpcBatch::STAGE_STARTUP)] : []),
            ]);

            // First fill wins: never overwrite an existing Master Produk shelf-life from a batch.
            MasterProduct::query()
                ->whereKey($batch->master_product_id)
                ->whereNull('shelf_life_months')
                ->update(['shelf_life_months' => $shelfLifeMonths]);

            return $startupCheck;
        });

        // Measurement only (AI read vs final value). Must never break or delay the operator's save.
        try {
            app(RecordStartupAiFeedback::class)->handle($batch, $aiApplied);
        } catch (\Throwable $e) {
            report($e);
        }

        return $startupCheck;
    }
}
