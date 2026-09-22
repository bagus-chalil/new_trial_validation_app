<?php

namespace App\Http\Requests\Admin;

use App\Models\MasterOption;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Rename (+ sort_order/is_active edit) action for one existing
 * `master_options` (type=reviewer_department) row — Phase 1 of the
 * RBAC/Team-master redesign (see memory rbac_team_lane_redesign_2026_09_22).
 * Follows the shape of StoreReviewerDepartmentRequest, plus a
 * case-insensitive uniqueness check (excluding the row being edited) since,
 * unlike Store's upsert-by-name flow, a rename must reject colliding with a
 * *different* existing row rather than silently merging into it.
 */
class UpdateReviewerDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-access-rights');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = User::normalizeDepartment($this->string('name')->toString());
            if ($name === '') {
                return;
            }

            $current = $this->route('reviewerDepartment');
            $currentId = is_object($current) ? $current->id : $current;

            $duplicate = MasterOption::query()
                ->where('type', 'reviewer_department')
                ->where('is_active', 1)
                ->whereRaw('UPPER(TRIM(name)) = ?', [$name])
                ->when($currentId, fn ($query) => $query->where('id', '!=', $currentId))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('name', 'Nama tim reviewer ini sudah dipakai.');
            }
        });
    }
}
