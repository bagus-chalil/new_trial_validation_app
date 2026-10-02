<?php

namespace App\Http\Requests\Trials;

use App\Models\Trial;
use App\Models\TrialAdditionalAttachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreTrialAdditionalAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail((int) $this->route('trial'));

        return Gate::allows('uploadAdditionalAttachment', $trial);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $extensions = implode(',', array_unique(array_merge(
            array_values(TrialAdditionalAttachment::ALLOWED_MIME_TO_EXTENSION),
            ['jpeg'],
        )));

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.TrialAdditionalAttachment::MAX_PER_TRIAL],
            'files.*' => [
                'file',
                'max:'.TrialAdditionalAttachment::MAX_SIZE_KB,
                'mimetypes:'.implode(',', array_keys(TrialAdditionalAttachment::ALLOWED_MIME_TO_EXTENSION)),
                'extensions:'.$extensions,
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The per-trial cap counts files already stored, so it can't be a plain
     * `max:` rule on the submitted array alone.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $existing = TrialAdditionalAttachment::where('trial_id', (int) $this->route('trial'))->count();
            $remaining = TrialAdditionalAttachment::MAX_PER_TRIAL - $existing;
            $submitted = count((array) $this->file('files', []));

            if ($submitted > $remaining) {
                $validator->errors()->add('files', $remaining > 0
                    ? __('messages.validation.additional_remaining', ['max' => TrialAdditionalAttachment::MAX_PER_TRIAL, 'remaining' => $remaining])
                    : __('messages.validation.additional_limit_reached', ['max' => TrialAdditionalAttachment::MAX_PER_TRIAL]));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => __('messages.validation.additional_required'),
            'files.max' => __('messages.validation.additional_max_per_upload', ['max' => TrialAdditionalAttachment::MAX_PER_TRIAL]),
            'files.*.max' => __('messages.validation.additional_too_large'),
            'files.*.mimetypes' => __('messages.validation.additional_file_type'),
            'files.*.extensions' => __('messages.validation.additional_file_type'),
        ];
    }
}
