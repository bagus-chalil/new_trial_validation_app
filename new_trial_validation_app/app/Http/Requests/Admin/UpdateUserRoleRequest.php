<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-access-rights');
    }

    /**
     * The frontend's Review Team <Select> uses a "__none" sentinel value for
     * "no review team" (Radix reserves an empty string internally for "no
     * selection", so it can't be used as a real item value) — normalize it
     * to null here before validation/persistence.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('review_unit') === '__none') {
            $this->merge(['review_unit' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:50'],
            'review_unit' => ['nullable', Rule::in(User::reviewerDepartmentCodes())],
        ];
    }
}
