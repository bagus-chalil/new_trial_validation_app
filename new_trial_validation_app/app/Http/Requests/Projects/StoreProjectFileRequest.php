<?php

namespace App\Http\Requests\Projects;

use App\Models\ProjectQuotation;
use App\Models\ProjectStage;
use App\Support\ProjectOptions;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectFileRequest extends ProjectChildRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'context' => ['required', Rule::in(ProjectOptions::FILE_CONTEXTS)],
            'stage_id' => ['nullable', 'integer'],
            'quotation_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:'.(ProjectOptions::MAX_UPLOAD_BYTES / 1024)],
        ];
    }

    /**
     * Context target must belong to this project (a stale/forged id from
     * another project must not attach a file there).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $projectId = (int) $this->route('project');

            if ($this->input('context') === 'stage'
                && ! ProjectStage::where('project_id', $projectId)->whereKey((int) $this->input('stage_id'))->exists()) {
                $validator->errors()->add('stage_id', __('projects.messages.stage_required'));
            }

            if ($this->input('context') === 'quotation'
                && ! ProjectQuotation::where('project_id', $projectId)->whereKey((int) $this->input('quotation_id'))->exists()) {
                $validator->errors()->add('quotation_id', __('projects.messages.quotation_required'));
            }
        }];
    }
}
