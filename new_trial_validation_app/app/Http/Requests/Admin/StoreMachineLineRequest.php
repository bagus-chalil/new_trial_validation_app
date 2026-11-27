<?php

namespace App\Http\Requests\Admin;

use App\Models\MachineLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMachineLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-machines');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $id = $this->integer('id') ?: null;

        return [
            'id' => ['nullable', 'integer', 'min:1'],
            // Blank on create = generated; kept as-is on update.
            'line_code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('mc_lines', 'line_code')->ignore($id)],
            'category' => ['required', Rule::in(MachineLine::CATEGORIES)],
            'line_type' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:200'],
            'plant_id' => ['nullable', 'integer', Rule::exists('mc_plants', 'id')->whereNull('deleted_at')],
            'building' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', 'string', 'max:50'],
            'area' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(MachineLine::STATUSES)],
            'products' => ['nullable', 'string', 'max:2000'],
            'rated_capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', Rule::in(MachineLine::CAPACITY_UNITS)],
            'pack_format' => ['nullable', 'string', 'max:255'],
            'automation_level' => ['nullable', Rule::in(MachineLine::AUTOMATION)],
            'manpower' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'pic' => ['nullable', 'string', 'max:120'],
            'layout_link' => ['nullable', 'string', 'max:500'],
            'tooling_link' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
