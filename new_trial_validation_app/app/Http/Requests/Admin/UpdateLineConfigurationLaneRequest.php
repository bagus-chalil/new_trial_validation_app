<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase 3 of the RBAC/Team-master redesign (see memory
 * rbac_team_lane_redesign_2026_09_22). Edits one existing
 * `line_configuration_lanes` row's display label and required team —
 * Super-Admin-only, same trust tier as Access Rights (reuses the
 * `manage-access-rights` Gate).
 */
class UpdateLineConfigurationLaneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-access-rights');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'required_team_id' => [
                'nullable', 'integer',
                Rule::exists('master_options', 'id')
                    ->where('type', 'reviewer_department')
                    ->where('is_active', 1),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        // The frontend's Team <Select> posts an empty-string sentinel for
        // "Semua user" (no required team) — normalize it to null before
        // validation, same sentinel-handling pattern UpdateUserRoleRequest
        // already uses for review_team_id.
        if ($this->input('required_team_id') === '' || $this->input('required_team_id') === '__none') {
            $this->merge(['required_team_id' => null]);
        }
    }
}
