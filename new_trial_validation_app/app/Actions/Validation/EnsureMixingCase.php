<?php

namespace App\Actions\Validation;

use App\Models\Mixing;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Services\Validation\CaseNumbering;
use App\Support\ValidationCaseType;

/**
 * Puts a Mixing record under a MIX Case (vd_case_links kind `mixing`). Uses the
 * given Case, else the first MIX Case of the product, else creates one.
 */
class EnsureMixingCase
{
    public function __construct(private readonly CaseNumbering $numbering) {}

    public function __invoke(Mixing $mixing, ?int $userId = null, ?ValidationCase $case = null): ValidationCase
    {
        $link = ValidationCaseLink::query()->where('kind', 'mixing')->where('ref_key', (string) $mixing->id)->first();
        if ($link !== null) {
            return ValidationCase::query()->findOrFail($link->case_id);
        }

        $case ??= $mixing->product_id === null ? null : ValidationCase::query()
            ->whereNull('deleted_at')
            ->where('case_type', ValidationCaseType::MIX)
            ->where('product_id', $mixing->product_id)
            ->orderBy('id')
            ->first();

        $case ??= $this->numbering->create(
            ValidationCaseType::MIX,
            now()->format('ym'),
            $mixing->product_id,
            (string) $mixing->product_name,
            'manual',
            $userId,
        );

        ValidationCaseLink::create([
            'case_id' => $case->id,
            'kind' => 'mixing',
            'ref_id' => $mixing->id,
            'ref_key' => (string) $mixing->id,
            'linked_by' => $userId,
        ]);

        $mixing->case_id = $case->id;
        $mixing->save();

        return $case;
    }
}
