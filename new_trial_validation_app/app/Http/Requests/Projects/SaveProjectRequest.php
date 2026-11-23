<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Support\ProjectOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project
            ? (bool) $this->user()?->can('update', Project::findOrFail((int) $project))
            : (bool) $this->user()?->can('create', Project::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:80'],
            'category' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(ProjectOptions::PROJECT_STATUSES)],
            'priority' => ['required', Rule::in(ProjectOptions::PRIORITIES)],
            'pic_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            'area' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'po' => ['nullable', 'string', 'max:120'],
            'machine_option_id' => ['nullable', 'integer', Rule::exists('master_options', 'id')->where('type', 'machine_used')->where('is_active', 1)->whereNull('deleted_at')],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('is_active', 1)->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('projects.fields.name'),
            'code' => __('projects.fields.code'),
            'category' => __('projects.fields.category'),
            'status' => __('projects.fields.status'),
            'priority' => __('projects.fields.priority'),
            'pic_user_id' => __('projects.fields.pic'),
            'start_date' => __('projects.fields.start_date'),
            'end_date' => __('projects.fields.end_date'),
            'machine_option_id' => __('projects.fields.machine'),
            'product_id' => __('projects.fields.product'),
        ];
    }
}
