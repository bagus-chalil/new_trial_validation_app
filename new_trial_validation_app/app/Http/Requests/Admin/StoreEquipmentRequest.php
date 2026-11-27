<?php

namespace App\Http\Requests\Admin;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-machines');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'min:1'],
            'process_step' => ['nullable', 'integer', 'min:1', 'max:999'],
            'name' => ['required', 'string', 'max:200'],
            'equipment_type' => ['nullable', Rule::in(Equipment::TYPES)],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_no' => ['nullable', 'string', 'max:120'],
            'year_made' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'country' => ['nullable', 'string', 'max:80'],
            'rated_capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:30'],
            'max_capacity' => ['nullable', 'numeric', 'min:0'],
            'heads' => ['nullable', 'string', 'max:150'],
            'working_volume' => ['nullable', 'string', 'max:150'],
            'voltage' => ['nullable', 'string', 'max:80'],
            'compressed_air' => ['nullable', 'string', 'max:80'],
            'dimension' => ['nullable', 'string', 'max:100'],
            'weight_kg' => ['nullable', 'string', 'max:40'],
            'other_spec' => ['nullable', 'string', 'max:3000'],
            'condition' => ['nullable', Rule::in(Equipment::CONDITIONS)],
            'manual_status' => ['nullable', Rule::in(Equipment::MANUAL_STATUSES)],
            'manual_link' => ['nullable', 'string', 'max:500'],
            'drawing_link' => ['nullable', 'string', 'max:500'],
            'qty' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
