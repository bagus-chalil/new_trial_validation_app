<?php

namespace App\Actions\Validation;

use App\Models\ActivityLog;
use App\Models\ImBrandCode;
use App\Models\ImNumber;
use App\Models\Product;
use App\Models\User;
use App\Support\ImNumberFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues IM numbers (QA): IM-{BRAND}-{PRODUCT}[ V{n}]. The first version has
 * no suffix; a revision is the previous number + 1, records which IM it
 * replaces and marks that one Superseded. No approval step (the paper IM keeps
 * its own sign-off chain); every action is logged. Numbers are normalised and
 * unique, and a void number is never reused.
 */
class IssueImNumber
{
    /**
     * @param  array<string, mixed>  $data  brand_code, product_part, product_id?, fg_codes?, line_machine?, customer?, customer_ref?, issue_date?
     */
    public function issue(array $data, User $user): ImNumber
    {
        $brand = ImBrandCode::query()->where('code', strtoupper(trim((string) $data['brand_code'])))->where('is_active', true)->first();
        if ($brand === null) {
            throw ValidationException::withMessages(['brand_code' => __('numbers.im.brand_unknown')]);
        }

        $product = ImNumberFormat::product((string) $data['product_part']);
        $number = ImNumberFormat::compose($brand->code, $product, 1);

        return DB::transaction(function () use ($data, $user, $brand, $product, $number) {
            $this->assertFree($number, 'product_part');

            $im = ImNumber::create($this->attributes($data, $user) + [
                'number' => $number,
                'brand_code' => $brand->code,
                'product_part' => $product,
                'version_no' => 1,
                'status' => 'Active',
            ]);

            $this->log($user, 'ISSUE_IM', $im);

            return $im;
        });
    }

    /**
     * @param  array<string, mixed>  $data  revision_reason (required), issue_date?, fg_codes?, line_machine?, customer?, customer_ref?
     */
    public function revise(ImNumber $previous, array $data, User $user): ImNumber
    {
        return DB::transaction(function () use ($previous, $data, $user) {
            $previous = ImNumber::query()->lockForUpdate()->findOrFail($previous->id);

            if ($previous->status !== 'Active') {
                throw ValidationException::withMessages(['revision_reason' => __('numbers.im.not_active')]);
            }

            $version = $previous->version_no + 1;
            $number = ImNumberFormat::compose($previous->brand_code, $previous->product_part, $version);
            $this->assertFree($number, 'revision_reason');

            $data += [
                'product_id' => $previous->product_id,
                'fg_codes' => $previous->fg_codes,
                'line_machine' => $previous->line_machine,
                'customer' => $previous->customer,
                'customer_ref' => $previous->customer_ref,
            ];

            $im = ImNumber::create($this->attributes($data, $user) + [
                'number' => $number,
                'brand_code' => $previous->brand_code,
                'product_part' => $previous->product_part,
                'version_no' => $version,
                'replaces_id' => $previous->id,
                'replaces_number' => $previous->number,
                'status' => 'Active',
            ]);

            $previous->status = 'Superseded';
            $previous->save();

            $this->log($user, 'REVISE_IM', $im);

            return $im;
        });
    }

    public function void(ImNumber $im, string $reason, User $user): void
    {
        if ($im->status === 'Void') {
            return;
        }

        $im->status = 'Void';
        $im->void_reason = $reason;
        $im->save();

        $this->log($user, 'VOID_IM', $im);
    }

    private function assertFree(string $number, string $errorKey): void
    {
        if (ImNumber::query()->where('number', $number)->exists()) {
            throw ValidationException::withMessages([$errorKey => __('numbers.im.duplicate', ['number' => $number])]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, User $user): array
    {
        $product = ! empty($data['product_id']) ? Product::query()->find((int) $data['product_id']) : null;

        $fg = collect((array) ($data['fg_codes'] ?? []))
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($fg === [] && $product !== null && trim((string) $product->finish_good_code) !== '') {
            $fg = [strtoupper(trim($product->finish_good_code))];
        }

        return [
            'product_id' => $product?->id,
            'fg_codes' => $fg,
            'line_machine' => trim((string) ($data['line_machine'] ?? '')),
            'customer' => trim((string) ($data['customer'] ?? '')),
            'customer_ref' => trim((string) ($data['customer_ref'] ?? '')),
            'issue_date' => $data['issue_date'] ?? now()->toDateString(),
            'revision_reason' => isset($data['revision_reason']) ? trim((string) $data['revision_reason']) : null,
            'issued_by_user_id' => $user->id,
            'issued_by_name' => $user->name,
        ];
    }

    private function log(User $user, string $action, ImNumber $im): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'IM_NUMBER',
            'record_id' => (string) $im->id,
            'record_label' => $im->number,
            'old_data' => null,
            'new_data' => json_encode(['number' => $im->number, 'status' => $im->status, 'replaces' => $im->replaces_number]),
        ]);
    }
}
