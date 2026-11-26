<?php

namespace App\Actions\ImMaster;

use App\Actions\Validation\IssueImNumber;
use App\Actions\Validation\LinkCaseNumber;
use App\Models\ImMaster;
use App\Models\ImNumber;
use App\Models\ImRevision;
use App\Models\Product;
use App\Models\User;
use App\Models\ValidationCase;
use App\Support\ImLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts an IM master for one official FG Code with its first revision as a
 * Draft. The IM number comes from the Tahap 3 register: either a number that
 * is already issued and not yet used by any revision (onboarding an existing
 * IM), or a new one issued now.
 */
class CreateImMaster
{
    public function __construct(private readonly IssueImNumber $issuer, private readonly LinkCaseNumber $linker) {}

    /**
     * @param  array<string, mixed>  $data  fg_code, brand_code?, product_part?, product_id?, customer?, existing_im_number_id?, legacy_reference?, case_id?
     */
    public function __invoke(array $data, User $user): ImRevision
    {
        $fg = strtoupper(trim((string) $data['fg_code']));

        if (ImMaster::query()->where('fg_code', $fg)->exists()) {
            throw ValidationException::withMessages(['fg_code' => __('im_master.error.fg_exists', ['fg' => $fg])]);
        }

        $product = ! empty($data['product_id']) ? Product::query()->find((int) $data['product_id']) : null;

        return DB::transaction(function () use ($data, $user, $fg, $product) {
            $existing = null;
            if (! empty($data['existing_im_number_id'])) {
                $existing = ImNumber::query()->lockForUpdate()->find((int) $data['existing_im_number_id']);
                if ($existing === null || $existing->status !== 'Active'
                    || ImRevision::query()->where('im_number_id', $existing->id)->exists()) {
                    throw ValidationException::withMessages(['existing_im_number_id' => __('im_master.error.number_unavailable')]);
                }
                $number = $existing;
            } else {
                $number = $this->issuer->issue([
                    'brand_code' => (string) ($data['brand_code'] ?? ''),
                    'product_part' => (string) ($data['product_part'] ?? ''),
                    'product_id' => $product?->id,
                    'fg_codes' => [$fg],
                    'customer' => (string) ($data['customer'] ?? ''),
                ], $user);
            }

            $master = ImMaster::create([
                'fg_code' => $fg,
                'brand_code' => $number->brand_code,
                'product_part' => $number->product_part,
                'product_id' => $product?->id,
                'product_name' => $product === null ? '' : $product->product_name,
                'customer' => trim((string) ($data['customer'] ?? $number->customer)),
                'created_by_user_id' => $user->id,
                'created_by_name' => (string) ($user->name ?: $user->email),
            ]);

            $legacy = trim((string) ($data['legacy_reference'] ?? ''));

            $revision = ImRevision::create([
                'master_id' => $master->id,
                'revision_no' => 1,
                'im_number' => $number->number,
                'im_number_id' => $number->id,
                'status' => 'Draft',
                'description' => $product === null ? '' : $product->product_name,
                'customer' => $master->customer,
                'customer_ref' => $number->customer_ref,
                'line_label' => $number->line_machine,
                'issue_date' => now()->toDateString(),
                'effective_from' => now()->toDateString(),
                'revision_reason' => '',
                'replaces_number' => $number->replaces_number,
                'legacy_reference' => $legacy !== '' ? $legacy : null,
                'is_onboarded' => $existing !== null || $legacy !== '',
                'prepared_by_user_id' => $user->id,
                'prepared_by_name' => (string) ($user->name ?: $user->email),
                'review_round' => 1,
            ]);

            if (! empty($data['case_id'])) {
                $case = ValidationCase::query()->find((int) $data['case_id']);
                if ($case !== null) {
                    $this->linker->link($case, $number, $user);
                }
            }

            ImLog::write($user, 'CREATE_MASTER', $revision, ['fg_code' => $fg, 'onboarded' => $revision->is_onboarded]);

            return $revision;
        });
    }
}
