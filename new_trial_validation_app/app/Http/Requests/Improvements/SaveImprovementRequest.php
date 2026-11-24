<?php

namespace App\Http\Requests\Improvements;

use App\Models\Improvement;
use Illuminate\Foundation\Http\FormRequest;

class SaveImprovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $improvement = $this->route('improvement');

        return $improvement
            ? (bool) $this->user()?->can('update', Improvement::whereNull('deleted_at')->findOrFail((int) $improvement))
            : (bool) $this->user()?->can('create', Improvement::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'issue' => ['required', 'string', 'max:5000'],
            'background' => ['nullable', 'string', 'max:5000'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'goals' => ['nullable', 'string', 'max:5000'],
            'saving_mp' => ['nullable', 'string', 'max:255'],
            'quality_metric' => ['nullable', 'string', 'max:255'],
            'links' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('improvements.fields.title'),
            'issue' => __('improvements.fields.issue'),
            'department' => __('improvements.fields.department'),
        ];
    }
}
