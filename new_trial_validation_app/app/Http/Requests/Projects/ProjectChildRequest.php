<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/** Base for requests that change something inside a project: needs `update` on that project. */
abstract class ProjectChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::whereNull('deleted_at')->findOrFail((int) $this->route('project'));

        return (bool) $this->user()?->can('update', $project);
    }
}
