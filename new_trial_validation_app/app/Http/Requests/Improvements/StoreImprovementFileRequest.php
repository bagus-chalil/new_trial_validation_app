<?php

namespace App\Http\Requests\Improvements;

use App\Models\Improvement;
use App\Support\ProjectOptions;
use Illuminate\Foundation\Http\FormRequest;

class StoreImprovementFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail((int) $this->route('improvement'));
        $user = $this->user();

        // The owner (while editable) or a reviewer may attach files.
        return (bool) ($user?->can('update', $improvement) || $user?->can('review', $improvement));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:1000'],
            // "after" (result evidence) is for reviewers only, see ImprovementFileController.
            'phase' => ['nullable', 'in:before,after'],
            'file' => ['required', 'file', 'max:'.(ProjectOptions::MAX_UPLOAD_BYTES / 1024)],
        ];
    }
}
