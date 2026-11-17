<?php

namespace App\Http\Requests\Projects;

use App\Support\ProjectOptions;
use Illuminate\Validation\Rule;

class SaveProjectMilestoneRequest extends ProjectChildRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(ProjectOptions::MILESTONE_STATUSES)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('projects.milestone.name'),
            'due_date' => __('projects.milestone.due_date'),
            'status' => __('projects.fields.status'),
        ];
    }
}
