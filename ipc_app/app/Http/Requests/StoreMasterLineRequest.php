<?php

namespace App\Http\Requests;

use App\Services\MachineMaster\MachineLineDirectory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', Rule::exists('master_lines', 'id')],
            'category' => ['required', 'string', 'max:100'],
            'area' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            // Reference to the Machine Master line (new_trial_validation_app), e.g. SC-01. Optional.
            'machine_line_code' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== null && $value !== '' && ! MachineLineDirectory::exists((string) $value)) {
                    $fail('Kode line tidak ditemukan di Master Mesin (atau Master Mesin tidak dapat dibaca).');
                }
            }],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ];
    }
}
