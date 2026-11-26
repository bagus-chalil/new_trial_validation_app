<?php

namespace App\Actions\Validation;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use App\Models\ValidationAssessment;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Services\Validation\CaseNumbering;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Saves a Validation Assessment. A new Assessment MAKES the Case (number from
 * CaseNumbering, source `assessment`): the Case is the parent every later
 * document points to. Editing keeps the Case number; the Case product follows
 * the Assessment only while no trial is linked to the Case yet.
 */
class SaveValidationAssessment
{
    public function __construct(private readonly CaseNumbering $numbering) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function __invoke(?ValidationAssessment $assessment, array $data, User $user): ValidationAssessment
    {
        return DB::transaction(function () use ($assessment, $data, $user) {
            $product = isset($data['product_id'])
                ? Product::query()->where('id', $data['product_id'])->first()
                : null;

            $attributes = [
                'product_id' => $product?->id,
                'product_name' => trim((string) ($product === null ? ($data['product_name'] ?? '') : $product->product_name)),
                'receive_date' => $data['receive_date'] ?? null,
                'client' => trim((string) ($data['client'] ?? '')),
                'sales_month' => $data['sales_month'] ?? null,
                'qty_po' => isset($data['qty_po']) && $data['qty_po'] !== '' ? (int) $data['qty_po'] : null,
                'packaging_type' => trim((string) ($data['packaging_type'] ?? '')),
                'validation_scope' => array_values($data['validation_scope'] ?? []),
                'recommended_machine' => trim((string) ($data['recommended_machine'] ?? '')),
                'qco_no' => trim((string) ($data['qco_no'] ?? '')),
                'qco_link' => trim((string) ($data['qco_link'] ?? '')),
                // "N/A" wins: a date is meaningless when the material does not apply.
                'pm_na' => (bool) ($data['pm_na'] ?? false),
                'pm_received_date' => ($data['pm_na'] ?? false) ? null : ($data['pm_received_date'] ?? null),
                'bulk_na' => (bool) ($data['bulk_na'] ?? false),
                'bulk_received_date' => ($data['bulk_na'] ?? false) ? null : ($data['bulk_received_date'] ?? null),
                'risk_level' => $data['risk_level'],
                'notes' => $data['notes'] ?? null,
            ];

            if ($assessment === null && ($data['case_id'] ?? null) !== null) {
                // Complete an existing Case (made by the backfill from older trials): the number stays.
                $case = ValidationCase::query()->whereNull('deleted_at')->where('id', (int) $data['case_id'])->firstOrFail();

                $assessment = ValidationAssessment::create($attributes + [
                    'case_id' => $case->id,
                    'created_by_user_id' => $user->id,
                    'created_by_name' => $user->name,
                ]);

                $this->log($user, 'CREATE', $assessment, null, ['case_no' => $case->case_no, 'completed_existing_case' => true]);

                return $assessment;
            }

            if ($assessment === null) {
                $period = ($attributes['receive_date'] !== null ? Carbon::parse($attributes['receive_date']) : Carbon::now())->format('ym');

                $case = $this->numbering->create(
                    $data['case_type'],
                    $period,
                    $attributes['product_id'],
                    $attributes['product_name'],
                    'assessment',
                    $user->id,
                );

                $assessment = ValidationAssessment::create($attributes + [
                    'case_id' => $case->id,
                    'created_by_user_id' => $user->id,
                    'created_by_name' => $user->name,
                ]);

                $this->log($user, 'CREATE', $assessment, null, ['case_no' => $case->case_no]);

                return $assessment;
            }

            $old = $assessment->only(['product_name', 'client', 'risk_level', 'recommended_machine']);
            $assessment->fill($attributes)->save();

            $case = $assessment->case;
            $hasTrials = ValidationCaseLink::query()->where('case_id', $case->id)->where('kind', 'trial')->exists();
            if (! $hasTrials) {
                $case->product_id = $attributes['product_id'];
                $case->product_name = $attributes['product_name'];
                $case->save();
            }

            $this->log($user, 'UPDATE', $assessment, $old, $assessment->only(array_keys($old)));

            return $assessment;
        });
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $new
     */
    private function log(User $user, string $action, ValidationAssessment $assessment, ?array $old, array $new): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'VALIDATION_ASSESSMENT',
            'record_id' => (string) $assessment->id,
            'record_label' => $assessment->case->case_no ?? ('#'.$assessment->id),
            'old_data' => $old === null ? null : json_encode($old),
            'new_data' => json_encode($new),
        ]);
    }
}
