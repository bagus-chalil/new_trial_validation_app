<?php

namespace App\Services\Validation;

use App\Models\MasterOption;
use App\Models\ValidationAssessment;
use App\Models\ValidationCase;
use App\Support\ValidationCaseType;

/**
 * Pre-fills the trial form from a Case and its Assessment (Tahap 2: "create
 * Trial from Case"). Only values that still exist as active master options
 * are returned, so the form never starts with a value the server would
 * reject. Same idea as the Project-stage pre-fill of Fase 4.
 */
class CaseTrialPrefill
{
    /**
     * @return array{product_id: int|null, machine_used: array<int, string>, validation_scope: array<int, string>, product_type: string|null, validation_category: string|null, risk_level: string|null, pm_ready_date: string|null, bulk_ready_date: string|null}
     */
    public function build(ValidationCase $case): array
    {
        $case->loadMissing('product');
        $assessment = ValidationAssessment::query()->whereNull('deleted_at')->where('case_id', $case->id)->first();

        $product = $case->product;
        $productId = $product && $product->is_active && $product->deleted_at === null ? $product->id : null;

        $machines = $assessment === null
            ? []
            : $this->onlyActive('machine_used', preg_split('/[,;\n]+/', $assessment->recommended_machine) ?: []);

        return [
            'product_id' => $productId,
            'machine_used' => $machines,
            'validation_scope' => $assessment === null ? [] : $this->onlyActive('validation_scope', $assessment->validation_scope ?? []),
            'product_type' => $assessment === null ? null : ($this->onlyActive('product_type', [$assessment->packaging_type])[0] ?? null),
            'validation_category' => $this->category($case->case_type),
            'risk_level' => $assessment?->risk_level,
            'pm_ready_date' => $assessment?->pm_na ? null : $assessment?->pm_received_date?->toDateString(),
            'bulk_ready_date' => $assessment?->bulk_na ? null : $assessment?->bulk_received_date?->toDateString(),
        ];
    }

    /** NPD Cases suggest the first active category with "NPD" in its name; others stay open. */
    private function category(string $caseType): ?string
    {
        if ($caseType !== ValidationCaseType::NPD) {
            return null;
        }

        return MasterOption::query()
            ->where('type', 'validation_category')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->where('name', 'like', '%NPD%')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('name');
    }

    /**
     * @param  iterable<int, mixed>  $values
     * @return array<int, string>
     */
    private function onlyActive(string $type, iterable $values): array
    {
        $wanted = collect($values)->map(fn ($v) => trim((string) $v))->filter()->unique()->values();
        if ($wanted->isEmpty()) {
            return [];
        }

        $active = MasterOption::query()
            ->where('type', $type)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->whereIn('name', $wanted->all())
            ->pluck('name');

        return $wanted->filter(fn (string $v) => $active->contains($v))->values()->all();
    }
}
