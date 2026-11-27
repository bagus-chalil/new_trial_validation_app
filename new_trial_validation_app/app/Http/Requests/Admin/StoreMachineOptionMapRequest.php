<?php

namespace App\Http\Requests\Admin;

use App\Models\Equipment;
use App\Models\MachineOptionMap;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMachineOptionMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-machines');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'option_name' => ['required', 'string', 'max:190'],
            'not_applicable' => ['nullable', 'boolean'],
            'line_id' => ['nullable', 'integer', Rule::exists('mc_lines', 'id')->whereNull('deleted_at')],
            'equipment_id' => ['nullable', 'integer', Rule::exists('mc_equipment', 'id')->whereNull('deleted_at')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            if (! in_array((string) $this->input('option_name'), MachineOptionMap::optionNames(), true)) {
                $v->errors()->add('option_name', __('machines.mapping.errors.unknown_option'));

                return;
            }
            if ($this->boolean('not_applicable')) {
                return;
            }
            if (! $this->filled('line_id')) {
                $v->errors()->add('line_id', __('machines.mapping.errors.line_required'));

                return;
            }
            if ($this->filled('equipment_id')
                && ! Equipment::query()->whereKey($this->integer('equipment_id'))->where('line_id', $this->integer('line_id'))->exists()) {
                $v->errors()->add('equipment_id', __('machines.mapping.errors.equipment_line'));
            }
        });
    }
}
