<?php

namespace App\Http\Requests\Projects;

use App\Support\ProjectOptions;
use Illuminate\Validation\Rule;

class SaveProjectStageRequest extends ProjectChildRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'stage_type' => ['nullable', Rule::in(ProjectOptions::STAGE_TYPES)],
            'pic_name' => ['nullable', 'string', 'max:100'],
            'plan_start' => ['nullable', 'date'],
            'plan_end' => ['nullable', 'date', 'after_or_equal:plan_start'],
            'actual_start' => ['nullable', 'date'],
            'actual_end' => ['nullable', 'date', 'after_or_equal:actual_start'],
            'progress' => ['required', 'integer', 'between:0,100'],
            'is_parallel' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(ProjectOptions::STAGE_STATUSES)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('projects.stage.name'),
            'plan_start' => __('projects.stage.plan_start'),
            'plan_end' => __('projects.stage.plan_end'),
            'actual_start' => __('projects.stage.actual_start'),
            'actual_end' => __('projects.stage.actual_end'),
            'progress' => __('projects.stage.progress'),
            'status' => __('projects.fields.status'),
        ];
    }
}
